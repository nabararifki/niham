<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // FK: property_id
            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->nullOnDelete();

            // Tenant-scoped unique constraint
            $table->unique(['name', 'property_id']);
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
