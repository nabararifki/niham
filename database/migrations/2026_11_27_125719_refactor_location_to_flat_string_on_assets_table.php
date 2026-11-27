<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('location', 255)->nullable()->after('department_id');
            $table->index(['property_id', 'location']);
        });

        if (Schema::hasTable('locations') && Schema::hasColumn('assets', 'location_id')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('
                    UPDATE assets
                    SET location = locations.name
                    FROM locations
                    WHERE assets.location_id = locations.id
                      AND assets.location_id IS NOT NULL
                ');
            } else {
                DB::statement('
                    UPDATE assets
                    SET location = (
                        SELECT locations.name
                        FROM locations
                        WHERE locations.id = assets.location_id
                    )
                    WHERE location_id IS NOT NULL
                ');
            }

            $unmigratedCount = DB::table('assets')
                ->whereNotNull('location_id')
                ->whereNull('location')
                ->count();

            if ($unmigratedCount > 0) {
                throw new RuntimeException(
                    "Location backfill integrity check failed: {$unmigratedCount} asset(s) have non-null location_id but null location."
                );
            }

            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['location_id']);
                $table->dropIndex(['property_id', 'location_id']);
                $table->dropColumn('location_id');
            });
        }

        Schema::dropIfExists('locations');

        if (Schema::hasColumn('roles', 'perm_locations')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('perm_locations');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('roles', 'perm_locations')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('perm_locations')->default('no access')->after('perm_departments');
            });
        }

        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreignId('property_id')
                    ->nullable()
                    ->constrained('properties')
                    ->nullOnDelete();

                $table->unique(['name', 'property_id']);
                $table->index('property_id');
            });
        }

        if (! Schema::hasColumn('assets', 'location_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreignId('location_id')
                    ->nullable()
                    ->constrained('locations')
                    ->nullOnDelete();

                $table->index(['property_id', 'location_id']);
            });
        }

        if (Schema::hasColumn('assets', 'location')) {
            $pairs = DB::table('assets')
                ->select('property_id', 'location')
                ->whereNotNull('location')
                ->where('location', '<>', '')
                ->distinct()
                ->get();

            $now = now()->toDateTimeString();
            foreach ($pairs as $pair) {
                $locationId = DB::table('locations')->insertGetId([
                    'uuid' => (string) Str::orderedUuid(),
                    'name' => $pair->location,
                    'property_id' => $pair->property_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $query = DB::table('assets')->where('location', $pair->location);
                if ($pair->property_id === null) {
                    $query->whereNull('property_id');
                } else {
                    $query->where('property_id', $pair->property_id);
                }
                $query->update(['location_id' => $locationId]);
            }

            Schema::table('assets', function (Blueprint $table) {
                $table->dropIndex(['property_id', 'location']);
                $table->dropColumn('location');
            });
        }
    }
};
