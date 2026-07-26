<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Progress update interval (rows).
     */
    private const BATCH_SIZE = 500;

    /**
     * Cache TTL for progress tracking (seconds).
     */
    private const CACHE_TTL = 600;

    /**
     * @param int        $userId         Authenticated user ID
     * @param string     $tempFilePath   Relative path within 'local' disk (e.g. "temp/import_xxx.xlsx")
     * @param array      $mappingPayload The full mapping payload from the frontend
     * @param int|string $selectedSheet  Sheet index or name
     */
    public function __construct(
        public int $userId,
        public string $tempFilePath,
        public array $mappingPayload,
        public int|string $selectedSheet,
    ) {}

    /**
     * Cache key for this user's import progress.
     */
    private function progressKey(): string
    {
        return 'import_progress_' . $this->userId;
    }

    /**
     * Update progress in cache.
     */
    private function setProgress(string $status, int $processed, int $total, string $error = ''): void
    {
        $percentage = $total > 0 ? min(100, (int) round(($processed / $total) * 100)) : 0;

        Cache::put($this->progressKey(), [
            'status'     => $status,
            'percentage' => $percentage,
            'processed'  => $processed,
            'total'      => $total,
            'error'      => $error,
        ], self::CACHE_TTL);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Raise memory limit for large XLSX shared-string tables
        @ini_set('memory_limit', '512M');

        try {
            $this->processFile();
        } catch (\Throwable $e) {
            Log::error('ProcessImportJob failed: ' . $e->getMessage(), [
                'user_id' => $this->userId,
                'file'    => $this->tempFilePath,
                'trace'   => $e->getTraceAsString(),
            ]);

            Cache::put($this->progressKey(), [
                'status'     => 'failed',
                'percentage' => 0,
                'processed'  => 0,
                'total'      => 0,
                'error'      => $e->getMessage(),
            ], self::CACHE_TTL);
        } finally {
            // Cleanup temp file safely (use Storage so path is consistent)
            try {
                $currentProgress = Cache::get($this->progressKey());
                $isCancelled = ($currentProgress && ($currentProgress['status'] ?? '') === 'cancelled');

                if (!$isCancelled && Storage::disk('local')->exists($this->tempFilePath)) {
                    Storage::disk('local')->delete($this->tempFilePath);
                }
            } catch (\Throwable $cleanupErr) {
                Log::warning('Failed to cleanup temp file: ' . $cleanupErr->getMessage());
            }

            $currentProgress = Cache::get($this->progressKey());
            if ($currentProgress && !in_array($currentProgress['status'] ?? '', ['completed', 'failed', 'cancelled'])) {
                Log::warning('ProcessImportJob ended without terminal status. Forcing failed.', [
                    'user_id' => $this->userId,
                    'current' => $currentProgress,
                ]);
                Cache::put($this->progressKey(), [
                    'status'     => 'failed',
                    'percentage' => $currentProgress['percentage'] ?? 0,
                    'processed'  => $currentProgress['processed'] ?? 0,
                    'total'      => $currentProgress['total'] ?? 0,
                    'error'      => 'Job ended unexpectedly without completion.',
                ], self::CACHE_TTL);
            }
        }
    }

    /**
     * Core streaming logic: reads via OpenSpout, maps columns, streams to DB.
     */
    private function processFile(): void
    {
        if (!Storage::disk('local')->exists($this->tempFilePath)) {
            throw new \RuntimeException('Temporary import file not found: ' . $this->tempFilePath);
        }
        $fullPath  = Storage::disk('local')->path($this->tempFilePath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        $user        = User::with('department')->findOrFail($this->userId);
        $isExecutive = $user->hasExecutiveOversight();

        // ── Resolve property_id for tenant isolation in the staging table ─
        $importState = Cache::get('import_state_' . $this->userId) ?? [];
        $propertyId  = $user->isSuperAdmin()
            ? ($importState['property_id'] ?? $user->activePropertyId())
            : $user->property_id;

        if (!$propertyId) {
            throw new \RuntimeException('Cannot resolve property_id for user ' . $this->userId . '. Import aborted.');
        }

        $expectedHeader = $importState['true_header'] ?? [];
        $mapping        = $this->mappingPayload['mapping'] ?? [];

        $options = $extension === 'csv' ? new \OpenSpout\Reader\CSV\Options() : new \OpenSpout\Reader\XLSX\Options();
        $reader  = $extension === 'csv' ? new \OpenSpout\Reader\CSV\Reader($options) : new \OpenSpout\Reader\XLSX\Reader($options);

        $reader->open($fullPath);

        $header        = null;
        $processedRows = 0;
        $sheetIndex    = 0;
        $targetSheet   = is_numeric($this->selectedSheet) ? (int) $this->selectedSheet : 0;
        $resultsChunk  = [];
        $now           = now()->toDateTimeString();

        // Clear any existing staging rows for this user+property session
        DB::table('temporary_asset_imports')
            ->where('user_id', $this->userId)
            ->where('property_id', $propertyId)
            ->delete();

        // Pre-count data rows with a lightweight pass (no mapping, no DB writes)
        // so the progress bar can show a meaningful percentage.
        $totalRows = $this->countDataRows($fullPath, $extension, $targetSheet, $expectedHeader);

        $this->setProgress('processing', 0, $totalRows);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheetIndex !== $targetSheet) {
                $sheetIndex++;
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(function ($val) {
                    if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d');
                    return is_string($val) ? trim($val) : (string) $val;
                }, $row->toArray());

                if ($header === null) {
                    if (!empty($expectedHeader) && count(array_intersect($cells, $expectedHeader)) >= 2) {
                        $header = $cells;
                        continue;
                    }
                    if (empty($expectedHeader) && count(array_filter($cells)) >= 2) {
                        $header = $cells;
                        continue;
                    }
                    continue;
                }

                $getCombined = function (string $fieldId) use ($mapping, $header, $cells): string {
                    $mapInfo = $mapping[$fieldId] ?? null;
                    if (!$mapInfo || empty($mapInfo['columns'])) return '';
                    $vals = [];
                    foreach ($mapInfo['columns'] as $colName) {
                        $colIdx = array_search($colName, $header);
                        if ($colIdx !== false && isset($cells[$colIdx]) && $cells[$colIdx] !== '') {
                            $vals[] = $cells[$colIdx];
                        }
                    }
                    return implode($mapInfo['separator'] ?? ' ', $vals);
                };

                $statusRaw = strtolower($getCombined('status'));
                if (preg_match('/(aktif|active|in.?service|baik|good|bagus)/i', $statusRaw)) {
                    $status = 'in_service';
                } elseif (preg_match('/(rusak|broken|out.?of.?service|tidak.?aktif|inactive|non.?aktif)/i', $statusRaw)) {
                    $status = 'out_of_service';
                } elseif (preg_match('/(disposed|dibuang|dihapus|removed|scrap)/i', $statusRaw)) {
                    $status = 'disposed';
                } else {
                    $status = 'in_service';
                }

                $tag  = $getCombined('tag');
                $name = $getCombined('name');

                // Skip completely empty rows
                if ($tag === '' && $name === '' && $getCombined('category') === '' && $getCombined('serial_number') === '') {
                    continue;
                }

                $isEmpty   = empty($name) && empty($tag);
                $isInvalid = $isEmpty ? false : (empty($name));

                $resultsChunk[] = [
                    'user_id'          => $this->userId,
                    'property_id'      => $propertyId,
                    'tag'              => $tag,
                    'name'             => $name,
                    'category_id'      => null,
                    'department_id'    => !$isExecutive ? $user->department_id : null,
                    'status'           => $status,
                    'model'            => $getCombined('model'),
                    'serial_number'    => $getCombined('serial_number'),
                    'purchase_date'    => $this->sanitizeDate($getCombined('purchase_date')),
                    'purchase_cost'    => $getCombined('purchase_cost') ?: null,
                    'remarks'          => $getCombined('remarks'),
                    '_category_hint'   => $getCombined('category'),
                    '_department_hint' => !$isExecutive ? '' : $getCombined('department'),
                    'is_invalid'       => $isInvalid,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];

                $processedRows++;

                if (count($resultsChunk) >= self::BATCH_SIZE) {
                    DB::table('temporary_asset_imports')->insert($resultsChunk);
                    $resultsChunk = [];

                    // Cancellation check
                    $currentStatus = Cache::get('import_progress_' . $this->userId);
                    if (isset($currentStatus['status']) && $currentStatus['status'] === 'cancelled') {
                        return;
                    }
                    $this->setProgress('processing', $processedRows, $totalRows);
                }
            }
            break;
        }

        if (!empty($resultsChunk)) {
            DB::table('temporary_asset_imports')->insert($resultsChunk);
        }

        $reader->close();

        Cache::put($this->progressKey(), [
            'status'     => 'completed',
            'percentage' => 100,
            'processed'  => $processedRows,
            'total'      => $processedRows,
            'error'      => '',
        ], self::CACHE_TTL);

        Log::info('ProcessImportJob completed.', [
            'user_id'     => $this->userId,
            'rows'        => $processedRows,
            'property_id' => $propertyId,
        ]);
    }

    /**
     * Quickly count data rows in the target sheet without performing any mapping or DB work.
     *
     * Opens a separate reader instance, skips the header row, and counts non-empty rows.
     * This is a lightweight pass (no regex, no transformation) used only to establish
     * the total for the progress bar.
     */
    private function countDataRows(string $fullPath, string $extension, int $targetSheet, array $expectedHeader): int
    {
        $options = $extension === 'csv'
            ? new \OpenSpout\Reader\CSV\Options()
            : new \OpenSpout\Reader\XLSX\Options();
        $reader = $extension === 'csv'
            ? new \OpenSpout\Reader\CSV\Reader($options)
            : new \OpenSpout\Reader\XLSX\Reader($options);

        $reader->open($fullPath);

        $count       = 0;
        $headerFound = false;
        $sheetIndex  = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheetIndex !== $targetSheet) {
                $sheetIndex++;
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn($v) => is_string($v) ? trim($v) : (string) $v, $row->toArray());

                if (!$headerFound) {
                    $nonEmpty = count(array_filter($cells));
                    $overlap  = !empty($expectedHeader)
                        ? count(array_intersect($cells, $expectedHeader))
                        : $nonEmpty;

                    if ($overlap >= 2 || ($nonEmpty >= 2 && empty($expectedHeader))) {
                        $headerFound = true;
                    }
                    continue;
                }

                // Count rows that have at least one non-empty cell
                if (count(array_filter($cells)) > 0) {
                    $count++;
                }
            }
            break;
        }

        $reader->close();
        return $count;
    }

}
