<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add uuid (for public routing) and original_name (for download labels)
     * to the attachments table. These columns were referenced in tests and
     * the restore service but were absent from the original schema.
     */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('original_name')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'original_name']);
        });
    }
};
