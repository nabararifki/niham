<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
        'department_id',
        'property_id',
        'is_super_admin',
        'notify_department',
        'notify_all_properties',
        'notify_email',
        'email_frequency',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'notify_department' => 'boolean',
            'notify_all_properties' => 'boolean',
            'notify_email' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isRole(string $name): bool
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }
        return optional($this->role)->name === $name;
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Get the active property id for this user.
     * Super admin: from session or null (all).
     * Normal user: from their property_id.
     */
    public function activePropertyId(): ?int
    {
        if ($this->isSuperAdmin()) {
            return session('active_property_id');
        }

        return $this->property_id;
    }

    /**
     * Check if user has explicit string permission on a module.
     * New 9 strict string vocabulary:
     * 'no access', 'view only', 'create', 'update', 'delete', 
     * 'create & update', 'create & delete', 'update & delete', 'full access'
     */
    public function hasPermission(string $module, string $action): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }
        $perm = $this->role->{$module} ?? 'no access';

        if ($perm === 'full access') {
            return true;
        }
        if ($perm === 'no access') {
            return false;
        }

        // Implicit view if they have any permission other than 'no access'
        if ($action === 'view') {
            return true;
        }

        if ($action === 'create') {
            return in_array($perm, ['create', 'create & update', 'create & delete']);
        }

        if ($action === 'update') {
            return in_array($perm, ['update', 'create & update', 'update & delete']);
        }

        if ($action === 'delete') {
            return in_array($perm, ['delete', 'create & delete', 'update & delete']);
        }

        return false;
    }

    /**
     * Determine if user has executive oversight based on their department.
     */
    public function hasExecutiveOversight(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if (! $this->relationLoaded('department')) {
            $this->load('department');
        }
        return optional($this->department)->is_executive_oversight == true;
    }

    public function assetHistories()
    {
        return $this->hasMany(AssetHistory::class);
    }

    public function editedAssets()
    {
        return $this->hasMany(Asset::class, 'editor');
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
