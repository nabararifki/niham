<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the destination column for the "Model" value that the Bulk Add Manual
     * grid and the Smart Importer have always collected but could never store —
     * temporary_asset_imports.model existed, assets.model did not, so the value
     * was dropped at the final INSERT INTO assets.
     *
     * Mirrors serial_number: plain nullable varchar(255), no index (never queried
     * or filtered on, only displayed and exported).
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('model')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
