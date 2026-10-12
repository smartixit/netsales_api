<?php

namespace App\Models;

use App\Enums\RoleKey;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'roles';

    protected $primaryKey = 'role_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'role_key',
        'role_name',
        'role_desc',
        'is_system',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id', 'role_id');
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id', 'role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeByKey(Builder $query, RoleKey|string $roleKey): Builder
    {
        $key = $roleKey instanceof RoleKey ? $roleKey->value : $roleKey;

        return $query->where('role_key', $key);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('permission_key', $permissionKey);
        }

        return $this->permissions()->where('permission_key', $permissionKey)->exists();
    }
}
