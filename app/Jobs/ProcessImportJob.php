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
    private const BATCH_SIZE = 100;

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
                // If the job was cancelled, do NOT delete the temporary file yet,
                // because the user wants to return to the mapping page and re-submit it!
                $currentProgress = Cache::get($this->progressKey());
                $isCancelled = ($currentProgress && ($currentProgress['status'] ?? '') === 'cancelled');

                if (!$isCancelled && Storage::disk('local')->exists($this->tempFilePath)) {
                    Storage::disk('local')->delete($this->tempFilePath);
                }
            } catch (\Throwable $cleanupErr) {
                Log::warning('Failed to cleanup temp file: ' . $cleanupErr->getMessage());
            }

            // SAFETY NET: If somehow neither 'completed', 'failed', nor 'cancelled' was set,
            // ensure the frontend is never left polling forever.
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
     * Core streaming logic: reads via OpenSpout, maps columns, reports progress.
     */
    private function processFile(): void
    {
        // Resolve the absolute path from the Storage-relative path
        if (!Storage::disk('local')->exists($this->tempFilePath)) {
            throw new \RuntimeException('Temporary import file not found: ' . $this->tempFilePath);
        }
        $fullPath  = Storage::disk('local')->path($this->tempFilePath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // ── Resolve user + policies ───────────────────────────────────────
        $user = User::with('department')->findOrFail($this->userId);
        $isExecutive = $user->hasExecutiveOversight();

        // ── Retrieve import state (true_header from peek phase) ───────────
        $importState    = Cache::get('import_state_' . $this->userId) ?? [];
        $expectedHeader = $importState['true_header'] ?? [];

        // ── Mapping payload from frontend ─────────────────────────────────
        $mapping = $this->mappingPayload['mapping'] ?? [];

        // ── Phase 1: Count total rows for progress denominator ────────────
        $totalRows = $this->countDataRows($fullPath, $extension, $expectedHeader);
        $this->setProgress('processing', 0, $totalRows);

        // ── Phase 2: Stream and process ──────────────────────────────────
        $options = $extension === 'csv'
            ? new \OpenSpout\Reader\CSV\Options()
            : new \OpenSpout\Reader\XLSX\Options();
        $reader = $extension === 'csv'
            ? new \OpenSpout\Reader\CSV\Reader($options)
            : new \OpenSpout\Reader\XLSX\Reader($options);

        $reader->open($fullPath);

        $results       = [];
        $header        = null;
        $processedRows = 0;
        $sheetIndex    = 0;
        $targetSheet   = is_numeric($this->selectedSheet) ? (int) $this->selectedSheet : 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            // Navigate to the user-selected sheet
            if ($sheetIndex !== $targetSheet) {
                $sheetIndex++;
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(function ($val) {
                    if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d');
                    return is_string($val) ? trim($val) : (string) $val;
                }, $row->toArray());

                // ── Robust Header Detection ──────────────────────────
                if ($header === null) {
                    if (!empty($expectedHeader) && count(array_intersect($cells, $expectedHeader)) >= 2) {
                        $header = $cells;
                        continue;
                    }
                    if (empty($expectedHeader) && count(array_filter($cells)) >= 2) {
                        $header = $cells;
                        continue;
                    }
                    continue; // Skip pre-header rows
                }

                // ── Column combiner (respects user-defined separators) ──
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

                // ── Status normalization ─────────────────────────────
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

                // ── Build mapped row (hasExecutiveOversight policy) ───
                $mappedRow = [
                    'tag'              => $getCombined('tag'),
                    'name'             => $getCombined('name'),
                    'category_id'      => '',
                    'department_id'    => !$isExecutive ? $user->department_id : '',
                    'status'           => $status,
                    'model'            => $getCombined('model'),
                    'serial_number'    => $getCombined('serial_number'),
                    'purchase_date'    => $getCombined('purchase_date'),
                    'purchase_cost'    => $getCombined('purchase_cost'),
                    'remarks'          => $getCombined('remarks'),
                    '_category_hint'   => $getCombined('category'),
                    '_department_hint' => !$isExecutive ? '' : $getCombined('department'),
                ];

                // Skip completely empty rows
                if (trim(implode('', array_values($mappedRow))) !== '') {
                    $results[] = $mappedRow;
                }

                $processedRows++;

                // ── Progress checkpoint every BATCH_SIZE rows ────────
                if ($processedRows % self::BATCH_SIZE === 0) {
                    $currentStatus = Cache::get('import_progress_' . $this->userId);
                    if (isset($currentStatus['status']) && $currentStatus['status'] === 'cancelled') {
                        return; // Exits processFile() gracefully, skipping review caching and completed status
                    }
                    $this->setProgress('processing', $processedRows, $totalRows);
                }
            }

            break; // Process only the selected sheet
        }

        $reader->close();

        // ── Final: store results and force-mark completed ────────────────
        // This runs OUTSIDE the loop, guaranteeing it fires even for 0-row or
        // sub-BATCH_SIZE files (e.g. 15 rows < 500 batch threshold).
        Cache::put('import_review_' . $this->userId, $results, 1800);

        Cache::put($this->progressKey(), [
            'status'     => 'completed',
            'percentage' => 100,
            'processed'  => $processedRows,
            'total'      => $totalRows,
            'error'      => '',
        ], self::CACHE_TTL);

        Log::info('ProcessImportJob completed.', [
            'user_id' => $this->userId,
            'rows'    => $processedRows,
        ]);
    }

    /**
     * Count data rows (excluding header and pre-header rows) for accurate progress.
     * Uses a fast streaming pass without storing cell data.
     */
    private function countDataRows(string $fullPath, string $extension, array $expectedHeader): int
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
        $targetSheet = is_numeric($this->selectedSheet) ? (int) $this->selectedSheet : 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheetIndex !== $targetSheet) {
                $sheetIndex++;
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                if (!$headerFound) {
                    $cells = array_map(function ($val) {
                        if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d');
                        return is_string($val) ? trim($val) : (string) $val;
                    }, $row->toArray());

                    if (!empty($expectedHeader) && count(array_intersect($cells, $expectedHeader)) >= 2) {
                        $headerFound = true;
                        continue;
                    }
                    if (empty($expectedHeader) && count(array_filter($cells)) >= 2) {
                        $headerFound = true;
                        continue;
                    }
                    continue;
                }
                $count++;
            }
            break;
        }

        $reader->close();
        return $count;
    }
}
