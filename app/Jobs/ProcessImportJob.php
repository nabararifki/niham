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
use App\Traits\SanitizesImportDates;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SanitizesImportDates;

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
     * @param string     $attemptId      Identifies this dispatch; see isCurrentAttempt()
     */
    public function __construct(
        public int $userId,
        public string $tempFilePath,
        public array $mappingPayload,
        public int|string $selectedSheet,
        public string $attemptId,
    ) {}

    /**
     * Cache key for this user's import progress.
     */
    private function progressKey(): string
    {
        return 'import_progress_' . $this->userId;
    }

    /**
     * Whether this job is still the import the user is actually waiting on.
     *
     * The progress record is shared per user, so it doubles as the authority on
     * which attempt is current: processMapping() stamps every dispatch with a
     * fresh import_id, and cancel() flips the status. A job whose id no longer
     * matches has been superseded by a newer upload.
     *
     * Superseded and cancelled jobs must touch nothing shared — not the progress
     * record, not the staging table, and not the temp file, which the newer
     * attempt may be reading from at this very moment.
     */
    private function isCurrentAttempt(): bool
    {
        $progress = Cache::get($this->progressKey());

        if (! is_array($progress)) {
            return false;
        }

        if (($progress['import_id'] ?? null) !== $this->attemptId) {
            return false;
        }

        return ($progress['status'] ?? '') !== 'cancelled';
    }

    /**
     * Update progress in cache. No-op once this attempt is no longer current.
     */
    private function setProgress(string $status, int $processed, int $total, string $error = ''): void
    {
        if (! $this->isCurrentAttempt()) {
            return;
        }

        $percentage = $total > 0 ? min(100, (int) round(($processed / $total) * 100)) : 0;

        Cache::put($this->progressKey(), [
            'status'     => $status,
            'percentage' => $percentage,
            'processed'  => $processed,
            'total'      => $total,
            'error'      => $error,
            'import_id'  => $this->attemptId,
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

            $this->setProgress('failed', 0, 0, $e->getMessage());
        } finally {
            // Only the current attempt is allowed to clean up.
            //
            // A cancelled attempt deliberately keeps its temp file so the user can
            // retry from the mapping page. A superseded attempt must keep it too,
            // for a sharper reason: the newer import may be streaming that very
            // same file (cancel → reload → re-submit the same mapping page reuses
            // temp_file_path), and deleting it would break the live import.
            // Whatever is left behind is swept hourly by app:clean-abandoned-imports.
            if ($this->isCurrentAttempt()) {
                try {
                    if (Storage::disk('local')->exists($this->tempFilePath)) {
                        Storage::disk('local')->delete($this->tempFilePath);
                    }
                } catch (\Throwable $cleanupErr) {
                    Log::warning('Failed to cleanup temp file: ' . $cleanupErr->getMessage());
                }

                $currentProgress = Cache::get($this->progressKey());
                if (is_array($currentProgress) && !in_array($currentProgress['status'] ?? '', ['completed', 'failed'], true)) {
                    Log::warning('ProcessImportJob ended without terminal status. Forcing failed.', [
                        'user_id' => $this->userId,
                        'current' => $currentProgress,
                    ]);
                    $this->setProgress(
                        'failed',
                        $currentProgress['processed'] ?? 0,
                        $currentProgress['total'] ?? 0,
                        'Job ended unexpectedly without completion.',
                    );
                }
            }
        }
    }

    /**
     * Core streaming logic: reads via OpenSpout, maps columns, streams to DB.
     */
    private function processFile(): void
    {
        // Stop before the destructive DELETE below. A job that was cancelled, or
        // superseded by a newer upload, would otherwise wipe the staging rows of
        // the import the user is actually waiting on — the delete runs first and
        // used to be reached with no cancellation check in front of it at all.
        if (! $this->isCurrentAttempt()) {
            Log::info('ProcessImportJob skipped: attempt cancelled or superseded.', [
                'user_id'    => $this->userId,
                'attempt_id' => $this->attemptId,
                'file'       => $this->tempFilePath,
            ]);
            return;
        }

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

                    // Cancelled, or superseded by a newer upload → stop here.
                    if (! $this->isCurrentAttempt()) {
                        $reader->close();
                        return;
                    }
                    $this->setProgress('processing', $processedRows, $totalRows);
                }
            }
            break;
        }

        $reader->close();

        // Re-check before the final flush. For any file smaller than one chunk the
        // loop above never reaches a boundary, so this is the only cancellation
        // checkpoint such an import gets — previously it had none, and cancelling
        // a sub-500-row import silently imported it anyway.
        if (! $this->isCurrentAttempt()) {
            return;
        }

        if (!empty($resultsChunk)) {
            DB::table('temporary_asset_imports')->insert($resultsChunk);
        }

        $this->setProgress('completed', $processedRows, $processedRows);

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
