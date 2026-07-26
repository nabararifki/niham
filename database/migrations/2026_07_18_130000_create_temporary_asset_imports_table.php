<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging table for the Smart Importer pipeline.
 *
 * Replaces the Cache-based approach (which stored entire datasets as a
 * serialised BLOB in the cache table, causing RAM spikes and O(N) I/O
 * on every single-cell edit).
 *
 * Architecture:
 *  - ProcessImportJob streams rows directly into this table (chunked INSERTs).
 *  - review()          paginates via SQL SELECT — no PHP array in memory.
 *  - updateSingleRow() executes a targeted UPDATE on a single row by ID.
 *  - storeBatch()      uses INSERT INTO assets … SELECT FROM this table,
 *                      then DELETEs the staging rows.
 *
 * Tenant isolation is enforced at the DB level via the (user_id, property_id)
 * compound index and explicit WHERE clauses in every query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_asset_imports', function (Blueprint $table) {
            $table->id();

            // ── Tenant & ownership columns ─────────────────────────────────
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('property_id');

            // ── Asset data columns ─────────────────────────────────────────
            $table->string('tag', 64)->nullable()->default('');
            $table->string('name', 255)->nullable()->default('');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('status', 32)->default('in_service');
            $table->string('model', 255)->nullable()->default('');
            $table->string('serial_number', 255)->nullable()->default('');
            $table->string('purchase_date', 32)->nullable()->default('');
            $table->string('purchase_cost', 32)->nullable()->default('');
            $table->string('remarks', 255)->nullable()->default('');

            // ── Hint columns for Rapid-Add workflow ────────────────────────
            $table->string('_category_hint', 255)->nullable()->default('');
            $table->string('_department_hint', 255)->nullable()->default('');

            // ── Review validation flag (recalculated on update) ────────────
            $table->boolean('is_invalid')->default(true);

            $table->timestamps();

            // ── Foreign keys ───────────────────────────────────────────────
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // NOTE: category_id / department_id are NOT foreign-keyed here
            // because the user may correct them in the review UI.
            // FK integrity is enforced at the point of INSERT INTO assets.

            // ── Indexes ────────────────────────────────────────────────────
            $table->index(['user_id', 'property_id'], 'tai_user_property_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_asset_imports');
    }
};
