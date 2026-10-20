<?php

namespace App\Models;

use App\Enums\ValueType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Feature extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'features';

    protected $primaryKey = 'feature_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'feature_name',
        'feature_value_type',
        'feature_value',
        'feature_desc',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'feature_value_type' => ValueType::class,
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function planFeatures(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeature::class, 'feature_id', 'feature_id');
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(
            SubscriptionPlan::class,
            'subscription_plan_features',
            'feature_id',
            'plan_id'
        )->withTimestamps();
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function ensureActive(): void
    {
        if (! $this->isActive()) {
            throw ValidationException::withMessages([
                'feature_id' => ["الميزة النظامية [{$this->feature_name}] معطلة حالياً."],
            ]);
        }
    }
}
