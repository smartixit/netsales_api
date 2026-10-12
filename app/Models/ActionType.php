<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActionType extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'action_types';

    protected $primaryKey = 'action_type_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'action_type_key',
        'action_type_name',
        'action_type_desc',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'action_type_id', 'action_type_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('action_type_key', $key);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }
}
