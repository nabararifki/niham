<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CleanAbandonedImports extends Command
{
    protected $signature = 'app:clean-abandoned-imports';

    protected $description = 'Clean up abandoned temporary import files, cache records, and staging table rows.';

    public function handle(): int
    {
        $this->info('Starting clean up of abandoned imports...');

        $this->cleanTempFiles();
        $this->cleanExpiredCaches();
        $this->cleanStagingRows();

        $this->info('Clean up completed successfully.');
        return self::SUCCESS;
    }

    protected function cleanTempFiles(): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('temp')) {
            $this->info('No temp folder found on local storage disk.');
            return;
        }

        $files        = $disk->files('temp');
        $now          = time();
        $threshold    = 60 * 60; // 60 minutes
        $deletedCount = 0;

        foreach ($files as $file) {
            try {
                $lastModified = $disk->lastModified($file);
                $age          = $now - $lastModified;
                if ($age > $threshold) {
                    $disk->delete($file);
                    $deletedCount++;
                    $this->line("Deleted old temp file: {$file} (Age: " . round($age / 60) . " mins)");
                }
            } catch (\Throwable $e) {
                Log::error("Failed to delete temp import file: {$file}. Error: " . $e->getMessage());
                $this->error("Error deleting {$file}: {$e->getMessage()}");
            }
        }

        $this->info("Cleaned up {$deletedCount} abandoned temporary import file(s).");
    }

    protected function cleanExpiredCaches(): void
    {
        $defaultStore = config('cache.default');
        if ($defaultStore === 'database') {
            $prefix    = config('cache.prefix');
            $tableName = config('cache.stores.database.table', 'cache');
            try {
                $deletedCount = DB::table($tableName)
                    ->where(function ($query) use ($prefix) {
                        $query->where('key', 'like', $prefix . 'import_state_%')
                              ->orWhere('key', 'like', $prefix . 'import_review_%')
                              ->orWhere('key', 'like', $prefix . 'import_progress_%');
                    })
                    ->where('expiration', '<', now()->timestamp)
                    ->delete();
                $this->info("Cleaned up {$deletedCount} expired import cache record(s) from database cache table.");
            } catch (\Throwable $e) {
                Log::error("Failed to clean up expired import cache entries. Error: " . $e->getMessage());
                $this->error("Error cleaning database cache: {$e->getMessage()}");
            }
        } else {
            $this->line("Cache driver is set to '{$defaultStore}'. Automatic pruning of expired entries is handled by the cache driver natively.");
        }
    }

    /**
     * Remove staging rows older than 60 minutes.
     *
     * Rows that old are considered abandoned — the user navigated away,
     * the job failed, or the browser was closed before storeBatch() ran.
     * Prevents unbounded growth of the temporary_asset_imports table.
     */
    protected function cleanStagingRows(): void
    {
        try {
            $threshold    = now()->subMinutes(60);
            $deletedCount = DB::table('temporary_asset_imports')
                ->where('created_at', '<', $threshold)
                ->delete();
            $this->info("Cleaned up {$deletedCount} orphaned staging row(s) from temporary_asset_imports.");
        } catch (\Throwable $e) {
            Log::error("Failed to clean up orphaned staging rows. Error: " . $e->getMessage());
            $this->error("Error cleaning staging table: {$e->getMessage()}");
        }
    }
}
