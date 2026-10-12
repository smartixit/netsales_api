<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'permissions';

    protected $primaryKey = 'permission_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'module_id',
        'action_type_id',
        'permission_key',
        'permission_name',
        'permission_desc',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function actionType(): BelongsTo
    {
        return $this->belongsTo(ActionType::class, 'action_type_id', 'action_type_id');
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'permission_id', 'permission_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'permission_id', 'permission_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_permissions',
            'permission_id',
            'user_id'
        )->withTimestamps();
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeByModule(Builder $query, int $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('permission_key', $key);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }
}
