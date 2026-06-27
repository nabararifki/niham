<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CleanAbandonedImports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-abandoned-imports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up abandoned temporary import files and their corresponding cache records.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting clean up of abandoned imports...');

        // 1. Clean up temporary files older than 60 minutes
        $this->cleanTempFiles();

        // 2. Clean up expired import cache entries
        $this->cleanExpiredCaches();

        $this->info('Clean up completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Scan the local disk temp folder and delete files older than 60 minutes.
     */
    protected function cleanTempFiles(): void
    {
        $disk = Storage::disk('local');
        
        if (! $disk->exists('temp')) {
            $this->info('No temp folder found on local storage disk.');
            return;
        }

        $files = $disk->files('temp');
        $now = time();
        $threshold = 60 * 60; // 60 minutes
        $deletedCount = 0;

        foreach ($files as $file) {
            try {
                $lastModified = $disk->lastModified($file);
                $age = $now - $lastModified;

                if ($age > $threshold) {
                    $disk->delete($file);
                    $deletedCount++;
                    $this->line("Deleted old temp file: {$file} (Age: " . round($age / 60) . " mins)");
                }
            } catch (\Throwable $e) {
                // Log and print error but keep processing other files
                Log::error("Failed to delete temp import file: {$file}. Error: " . $e->getMessage());
                $this->error("Error deleting {$file}: {$e->getMessage()}");
            }
        }

        $this->info("Cleaned up {$deletedCount} abandoned temporary import file(s).");
    }

    /**
     * Clean up expired import cache keys from the database store.
     */
    protected function cleanExpiredCaches(): void
    {
        $defaultStore = config('cache.default');

        if ($defaultStore === 'database') {
            $prefix = config('cache.prefix');
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
}
