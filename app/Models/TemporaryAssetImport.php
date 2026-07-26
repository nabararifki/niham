<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the temporary_asset_imports staging table.
 *
 * This model intentionally does NOT use the BelongsToProperty trait
 * (which would add a global scope). All tenant isolation is enforced
 * through explicit WHERE user_id / property_id clauses in the
 * AssetImportController and ProcessImportJob.
 *
 * Columns mirror the asset structure plus two hint columns used by the
 * Rapid-Add workflow and one validation flag for the review UI.
 */
class TemporaryAssetImport extends Model
{
    protected $table = 'temporary_asset_imports';

    protected $fillable = [
        'user_id',
        'property_id',
        'tag',
        'name',
        'category_id',
        'department_id',
        'status',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_cost',
        'remarks',
        '_category_hint',
        '_department_hint',
        'is_invalid',
    ];

    protected function casts(): array
    {
        return [
            'is_invalid'    => 'boolean',
            'category_id'   => 'integer',
            'department_id' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /**
     * Scope to rows owned by a specific user AND property.
     * Always use this scope — never query without tenant constraints.
     */
    public function scopeForSession($query, int $userId, int $propertyId)
    {
        return $query->where('user_id', $userId)->where('property_id', $propertyId);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Determine if this staging row is valid for final INSERT.
     * A row is valid when it has a non-empty name AND a category_id.
     */
    public function isReadyForInsert(): bool
    {
        return !empty($this->name) && !empty($this->category_id);
    }

    /**
     * Recalculate and persist the is_invalid flag based on current field values.
     */
    public function recalculateValidity(): void
    {
        $isEmpty = empty($this->name)
            && empty($this->tag)
            && empty($this->category_id)
            && empty($this->department_id);

        if ($isEmpty) {
            $this->is_invalid = false;
        } else {
            $this->is_invalid = empty($this->name) || empty($this->category_id);
        }

        $this->save();
    }
}
