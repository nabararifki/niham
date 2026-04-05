<?php

namespace App\Models;

use App\Traits\BelongsToProperty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Location extends Model
{
    use BelongsToProperty, HasFactory, HasUuids;

    protected $fillable = ['name', 'code', 'notes', 'property_id'];

    public function assets()
    {
        return $this->hasMany(Asset::class);
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
