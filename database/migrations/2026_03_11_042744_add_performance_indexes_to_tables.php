<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add PostgreSQL GIN indexes to JSONB columns in asset_histories.
     *
     * GIN (Generalized Inverted Index) indexes are the correct index type for
     * JSONB columns in PostgreSQL. They enable efficient containment (@>) and
     * key-existence (?) queries. These must be created via raw DDL because
     * Laravel's Blueprint does not expose a GIN index type natively.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX asset_histories_original_gin ON asset_histories USING GIN (original jsonb_path_ops)');
        DB::statement('CREATE INDEX asset_histories_changes_gin  ON asset_histories USING GIN (changes  jsonb_path_ops)');
    }

    /**
     * Drop the GIN indexes.
     *
     * CRITICAL: Must use raw DB::statement('DROP INDEX ...'). PostgreSQL does NOT
     * support ALTER TABLE ... DROP INDEX — that is MySQL syntax. Laravel's
     * $table->dropIndex() emits ALTER TABLE, which will fail with a syntax error
     * on PostgreSQL for indexes created outside of Blueprint management.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS asset_histories_original_gin');
        DB::statement('DROP INDEX IF EXISTS asset_histories_changes_gin');
    }
};
