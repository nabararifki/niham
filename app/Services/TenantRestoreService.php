<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Http\File as HttpFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Tenant-Aware Restore Service
 *
 * Parses a Niham backup .zip produced by TenantBackupService and imports
 * all data into the currently active Property.
 *
 * Contract:
 *  - ALL database writes are wrapped in a single DB::transaction().
 *    Any failure rolls back the entire import automatically.
 *  - property_id is injected into every inserted/upserted record.
 *  - UUID collision → upsert (safe idempotent re-import).
 *  - Timestamps are normalised to MySQL DATETIME format (Y-m-d H:i:s) since
 *    Eloquent's toArray() emits ISO 8601 strings which MySQL rejects.
 *  - Media files are written AFTER the transaction commits successfully.
 *  - The temp extraction directory is unconditionally deleted in a finally block.
 *  - Paths are validated against zip-slip / path-traversal before any file op.
 */
class TenantRestoreService
{
    private const SUPPORTED_SCHEMA_VERSION = 1;

    private string $extractPath;

    /** @var array<string, mixed> */
    private array $manifest = [];

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly Property $property,
        private readonly string $zipPath,
    ) {}

    /**
     * Execute the full restore pipeline.
     *
     * @throws \RuntimeException|\JsonException|\Throwable
     */
    public function restore(): void
    {
        $this->extractPath = storage_path('app/restore-temp-'.Str::uuid());

        try {
            $this->extractZip();
            $this->loadAndValidate();

            DB::transaction(function (): void {
                $this->restorePropertySettings();
                $this->restoreRoles();
                $deptMap = $this->restoreDepartments();  // uuid → local id
                $catMap = $this->restoreCategories();   // uuid → local id
                $this->restoreAssets($catMap, $deptMap);
            });

            // Media written only after a successful DB commit.
            $this->extractMedia();

        } finally {
            File::deleteDirectory($this->extractPath);
        }
    }

    // ── Step 1: Extract ───────────────────────────────────────────────────────

    private function extractZip(): void
    {
        $zip = new ZipArchive;

        if ($zip->open($this->zipPath) !== true) {
            throw new \RuntimeException('Could not open the backup ZIP archive.');
        }

        mkdir($this->extractPath, 0755, true);
        $realBase = realpath($this->extractPath).DIRECTORY_SEPARATOR;

        // Zip-slip prevention: validate every entry before extraction.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $dest = realpath($this->extractPath.DIRECTORY_SEPARATOR.$name)
                 ?: ($realBase.ltrim($name, '/'));

            if (! str_starts_with($dest, $realBase)) {
                $zip->close();
                throw new \RuntimeException(
                    "Zip-slip attack detected: entry [{$name}] would escape the extraction directory."
                );
            }
        }

        $zip->extractTo($this->extractPath);
        $zip->close();
    }

    // ── Step 2: Load & validate ───────────────────────────────────────────────

    private function loadAndValidate(): void
    {
        $manifestPath = $this->extractPath.'/manifest.json';
        $dataPath = $this->extractPath.'/data.json';

        if (! file_exists($manifestPath) || ! file_exists($dataPath)) {
            throw new \RuntimeException('Invalid backup archive: missing manifest.json or data.json.');
        }

        $this->manifest = json_decode(
            file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR
        );

        $this->data = json_decode(
            file_get_contents($dataPath), true, 512, JSON_THROW_ON_ERROR
        );

        $schemaVersion = $this->manifest['niham_schema_version'] ?? 0;

        if ($schemaVersion !== self::SUPPORTED_SCHEMA_VERSION) {
            throw new \RuntimeException(sprintf(
                'Unsupported backup schema version [%s]. This installation requires version %d.',
                $schemaVersion,
                self::SUPPORTED_SCHEMA_VERSION,
            ));
        }
    }

    // ── Step 3a: Property settings ────────────────────────────────────────────

    /**
     * Restore display-level property settings.
     * The property `code` is deliberately NOT overwritten — it has a UNIQUE
     * constraint and is the property's identity. Branding paths are updated
     * after media extraction (Step 4).
     */
    private function restorePropertySettings(): void
    {
        $p = $this->data['property'] ?? [];

        $this->property->update([
            'name' => $p['name'] ?? $this->property->name,
            'address' => $p['address'] ?? $this->property->address,
            'accent_color' => $p['accent_color'] ?? $this->property->accent_color,
        ]);
    }

    // ── Step 3b: Roles ────────────────────────────────────────────────────────

    private function restoreRoles(): void
    {
        $now = now()->toDateTimeString();
        
        foreach ($this->data['roles'] ?? [] as $r) {
            $r['property_id'] = $this->property->id;
            $r['created_at'] = $this->toMySqlDatetime($r['created_at'] ?? $now);
            $r['updated_at'] = $now;

            // Ensure name is a string to prevent query builder from converting to WHERE IN
            // and to trigger PHP TypeError if it's an array (which tests expect for rollback validation)
            if (is_array($r['name'] ?? null)) {
                throw new \InvalidArgumentException('Role name cannot be an array');
            }

            $existing = DB::table('roles')->where('uuid', $r['uuid'])->first();
            if (! $existing) {
                $existing = DB::table('roles')
                    ->where('property_id', $this->property->id)
                    ->where('name', $r['name'])
                    ->first();
            }

            if ($existing) {
                DB::table('roles')->where('id', $existing->id)->update([
                    'uuid' => $r['uuid'],
                    'name' => $r['name'],
                    'perm_assets' => $r['perm_assets'] ?? 'view only',
                    'perm_users' => $r['perm_users'] ?? 'no access',
                    'perm_categories' => $r['perm_categories'] ?? 'no access',
                    'perm_departments' => $r['perm_departments'] ?? 'no access',
                    'perm_roles' => $r['perm_roles'] ?? 'no access',
                    'property_id' => $this->property->id,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('roles')->insert($r);
            }
        }
    }

    // ── Step 3c: Departments ──────────────────────────────────────────────────

    /** @return array<string, int>  uuid → local numeric id */
    private function restoreDepartments(): array
    {
        $now = now()->toDateTimeString();
        
        foreach ($this->data['departments'] ?? [] as $d) {
            $d['property_id'] = $this->property->id;
            $d['created_at'] = $this->toMySqlDatetime($d['created_at'] ?? $now);
            $d['updated_at'] = $now;

            if (is_array($d['code'] ?? null)) {
                throw new \InvalidArgumentException('Department code cannot be an array');
            }

            $existing = DB::table('departments')->where('uuid', $d['uuid'])->first();
            if (! $existing) {
                $existing = DB::table('departments')
                    ->where('property_id', $this->property->id)
                    ->where('code', $d['code'])
                    ->first();
            }

            if ($existing) {
                DB::table('departments')->where('id', $existing->id)->update([
                    'uuid' => $d['uuid'],
                    'name' => $d['name'],
                    'code' => $d['code'],
                    'notes' => $d['notes'] ?? null,
                    'is_executive_oversight' => $d['is_executive_oversight'] ?? false,
                    'property_id' => $this->property->id,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('departments')->insert($d);
            }
        }

        return DB::table('departments')
            ->where('property_id', $this->property->id)
            ->pluck('id', 'uuid')
            ->toArray();
    }

    // ── Step 3d: Categories ───────────────────────────────────────────────────

    /** @return array<string, int>  uuid → local numeric id */
    private function restoreCategories(): array
    {
        $now = now()->toDateTimeString();
        
        foreach ($this->data['categories'] ?? [] as $c) {
            $c['property_id'] = $this->property->id;
            $c['created_at'] = $this->toMySqlDatetime($c['created_at'] ?? $now);
            $c['updated_at'] = $now;

            if (is_array($c['code'] ?? null)) {
                throw new \InvalidArgumentException('Category code cannot be an array');
            }

            $existing = DB::table('categories')->where('uuid', $c['uuid'])->first();
            if (! $existing) {
                $existing = DB::table('categories')
                    ->where('property_id', $this->property->id)
                    ->where('code', $c['code'])
                    ->first();
            }

            if ($existing) {
                DB::table('categories')->where('id', $existing->id)->update([
                    'uuid' => $c['uuid'],
                    'name' => $c['name'],
                    'code' => $c['code'],
                    'notes' => $c['notes'] ?? null,
                    'property_id' => $this->property->id,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('categories')->insert($c);
            }
        }

        return DB::table('categories')
            ->where('property_id', $this->property->id)
            ->pluck('id', 'uuid')
            ->toArray();
    }

    // ── Step 3e: Assets ───────────────────────────────────────────────────────

    /**
     * @param  array<string, int>  $catMap  category uuid → local numeric id
     * @param  array<string, int>  $deptMap  department uuid → local numeric id
     */
    private function restoreAssets(array $catMap, array $deptMap): void
    {
        $now = now()->toDateTimeString();
        $assetRows = [];
        $attachRows = [];   // asset_uuid → attachment data (or null)
        $histRows = [];   // asset_uuid → list of history rows

        foreach ($this->data['assets'] ?? [] as $a) {
            $uuid = $a['uuid'] ?? null;
            if (! $uuid) {
                continue;
            }

            $attachRows[$uuid] = $a['attachment'] ?? null;
            $histRows[$uuid] = $a['histories'] ?? [];

            $categoryId = isset($a['category_uuid']) ? ($catMap[$a['category_uuid']] ?? null) : null;
            $departmentId = isset($a['department_uuid']) ? ($deptMap[$a['department_uuid']] ?? null) : null;

            $row = $this->omit($a, [
                'attachment', 'histories', 'category_uuid', 'department_uuid',
            ]);

            $row['property_id'] = $this->property->id;
            $row['category_id'] = $categoryId;
            $row['department_id'] = $departmentId;
            $row['editor'] = null;
            $row['created_at'] = $this->toMySqlDatetime($row['created_at'] ?? $now);
            $row['updated_at'] = $now;

            // Normalise date-only cast columns
            $row['purchase_date'] = $this->toMySqlDate($row['purchase_date'] ?? null);
            $row['warranty_date'] = $this->toMySqlDate($row['warranty_date'] ?? null);

            // Normalise soft-delete timestamp
            $row['deleted_at'] = isset($row['deleted_at'])
                ? $this->toMySqlDatetime($row['deleted_at'])
                : null;

            $assetRows[] = $row;
        }

        if (! empty($assetRows)) {
            DB::table('assets')->upsert(
                $assetRows,
                uniqueBy: ['uuid'],
                update: [
                    'tag', 'name', 'category_id', 'department_id', 'status',
                    'serial_number', 'purchase_date', 'warranty_date', 'purchase_cost',
                    'vendor', 'desc', 'remarks', 'deleted_at', 'property_id', 'updated_at',
                ],
            );
        }

        $assetIdMap = DB::table('assets')
            ->whereIn('uuid', array_keys($attachRows))
            ->pluck('id', 'uuid')
            ->toArray();

        $this->restoreAttachments($attachRows, $assetIdMap, $now);
        $this->restoreHistories($histRows, $assetIdMap, $now);
    }

    /**
     * @param  array<string, array<string,mixed>|null>  $attachRows
     * @param  array<string, int>  $assetIdMap
     */
    private function restoreAttachments(array $attachRows, array $assetIdMap, string $now): void
    {
        foreach ($attachRows as $assetUuid => $attach) {
            if (! $attach || ! isset($assetIdMap[$assetUuid])) {
                continue;
            }

            $assetId = $assetIdMap[$assetUuid];

            DB::table('attachments')->upsert(
                [[
                    'asset_id' => $assetId,
                    'path' => $attach['path'],
                    'type' => $attach['type'] ?? null,
                    'created_at' => $this->toMySqlDatetime($attach['created_at'] ?? $now),
                    'updated_at' => $now,
                ]],
                uniqueBy: ['asset_id'],
                update: ['path', 'type', 'updated_at'],
            );
        }
    }

    /**
     * @param  array<string, list<array<string,mixed>>>  $histRows
     * @param  array<string, int>  $assetIdMap
     */
    private function restoreHistories(array $histRows, array $assetIdMap, string $now): void
    {
        foreach ($histRows as $assetUuid => $histories) {
            if (empty($histories) || ! isset($assetIdMap[$assetUuid])) {
                continue;
            }

            $assetId = $assetIdMap[$assetUuid];

            DB::table('asset_histories')->where('asset_id', $assetId)->delete();

            $insertRows = array_map(function (array $h) use ($assetId, $now): array {
                return [
                    'asset_id' => $assetId,
                    'user_id' => null,
                    'action' => $h['action'] ?? '',
                    'original' => is_array($h['original'] ?? null)
                        ? json_encode($h['original'], JSON_THROW_ON_ERROR)
                        : ($h['original'] ?? null),
                    'changes' => is_array($h['changes'] ?? null)
                        ? json_encode($h['changes'], JSON_THROW_ON_ERROR)
                        : ($h['changes'] ?? null),
                    'created_at' => $this->toMySqlDatetime($h['created_at'] ?? $now),
                    'updated_at' => $now,
                ];
            }, $histories);

            DB::table('asset_histories')->insert($insertRows);
        }
    }

    // ── Step 4: Extract media ─────────────────────────────────────────────────

    private function extractMedia(): void
    {
        $disk = Storage::disk('public');
        $mediaBase = $this->extractPath.DIRECTORY_SEPARATOR.'media';

        if (! is_dir($mediaBase)) {
            return;
        }

        $propData = $this->data['property'] ?? [];
        $logoFinal = null;
        $bgFinal = null;

        foreach (['logo_path', 'background_image_path'] as $field) {
            $relativePath = $propData[$field] ?? null;

            if (! is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $srcFile = $this->secureJoin($mediaBase, $relativePath);
            if (! $srcFile || ! file_exists($srcFile)) {
                continue;
            }

            $currentPath = $this->property->$field;
            if ($currentPath && $currentPath !== $relativePath && $disk->exists($currentPath)) {
                $disk->delete($currentPath);
            }

            $disk->putFileAs(
                dirname($relativePath),
                new HttpFile($srcFile),
                basename($relativePath),
            );

            match ($field) {
                'logo_path' => $logoFinal = $relativePath,
                'background_image_path' => $bgFinal = $relativePath,
            };
        }

        $brandingUpdate = array_filter([
            'logo_path' => $logoFinal,
            'background_image_path' => $bgFinal,
        ], static fn (?string $v): bool => $v !== null);

        if (! empty($brandingUpdate)) {
            $this->property->update($brandingUpdate);
        }

        foreach ($this->data['assets'] ?? [] as $assetRow) {
            $relativePath = $assetRow['attachment']['path'] ?? null;

            if (! is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $srcFile = $this->secureJoin($mediaBase, $relativePath);
            if (! $srcFile || ! file_exists($srcFile)) {
                continue;
            }

            $disk->putFileAs(
                dirname($relativePath),
                new HttpFile($srcFile),
                basename($relativePath),
            );
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /**
     * Normalise any datetime string — ISO 8601, Carbon, or MySQL — to
     * MySQL's required `Y-m-d H:i:s` format. Returns null if input is null/empty.
     */
    private function toMySqlDatetime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalise a date-only value (`Y-m-d`). Returns null if null/empty.
     */
    private function toMySqlDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Safely join $base and $relative; return null if the result escapes $base.
     */
    private function secureJoin(string $base, string $relative): ?string
    {
        $relative = ltrim(
            str_replace(['../', '..'.DIRECTORY_SEPARATOR], '', $relative),
            '/\\'
        );

        $full = rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;
        $real = realpath($full) ?: $full;

        if (! str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $full;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function omit(array $data, array $keys): array
    {
        return array_diff_key($data, array_flip($keys));
    }
}
