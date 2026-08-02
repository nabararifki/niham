<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which cells could not be converted to their target column's type.
 *
 * A JSON object keyed by field name, holding the raw text that failed:
 * {"purchase_date":"ABC123","purchase_cost":"n/a"}. Null for the overwhelming
 * majority of rows, where everything converted cleanly.
 *
 * Kept out of is_invalid on purpose. These rows are importable — storeBatch()
 * already drops an unusable date or cost at save — so blocking them would turn
 * one messy column into a page of rows the user has to clear by hand. What was
 * missing was any way to see that the drop happened at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_asset_imports', function (Blueprint $table) {
            $table->text('_coercion_notes')->nullable()->after('_department_hint');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_asset_imports', function (Blueprint $table) {
            $table->dropColumn('_coercion_notes');
        });
    }
};
