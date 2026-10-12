<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'modules';

    protected $primaryKey = 'module_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'module_key',
        'module_name',
        'module_icon',
        'module_desc',
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
        return $this->hasMany(Permission::class, 'module_id', 'module_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('module_key', $key);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }
}
