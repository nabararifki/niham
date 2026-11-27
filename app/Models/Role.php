<?php

namespace App\Models;

use App\Traits\BelongsToProperty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Role extends Model
{
    use BelongsToProperty, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'property_id',
        'perm_assets',
        'perm_users',
        'perm_categories',
        'perm_departments',
        'perm_roles',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
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
