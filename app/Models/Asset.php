<?php

namespace App\Models;

use App\Traits\BelongsToProperty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Str;

class Asset extends Model
{
    use BelongsToProperty, HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'uuid',
        'tag',
        'name',
        'category_id',
        'department_id',
        'status',
        'serial_number',
        'purchase_date',
        'warranty_date',
        'purchase_cost',
        'vendor',
        'desc',
        'remarks',
        'editor',
        'property_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_date' => 'date',
        ];
    }

    protected static function booted()
    {
        static::deleting(function ($asset) {
            if ($asset->isForceDeleting()) {
                $attachment = $asset->attachments()->first();
                if ($attachment) {
                    if (\Storage::disk('public')->exists($attachment->path)) {
                        \Storage::disk('public')->delete($attachment->path);
                    }
                    $attachment->delete();
                }
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function attachments()
    {
        return $this->hasOne(Attachment::class);
    }

    public function editorUser()
    {
        return $this->belongsTo(User::class, 'editor');
    }

    public function histories()
    {
        return $this->hasMany(AssetHistory::class);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
