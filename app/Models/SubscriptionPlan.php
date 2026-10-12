<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'subscription_plans';

    protected $primaryKey = 'plan_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'plan_uuid',
        'plan_name',
        'plan_price',
        'plan_discount',
        'plan_validity',
        'plan_desc',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'plan_price' => 'decimal:2',
            'plan_discount' => 'decimal:2',
            'plan_validity' => 'integer',
            'status' => 'boolean',
        ];
    }

    // Auto generate subscription_plan_uuid
    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            if (empty($plan->plan_uuid)) {
                $plan->plan_uuid = BusinessIdentifierGenerator::forSubscriptionPlan($plan->plan_id);
            }
        });
    }

    // Relationships
    public function planFeatures(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeature::class, 'plan_id', 'plan_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(
            Feature::class,
            'subscription_plan_features',
            'plan_id',
            'feature_id'
        )->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ProviderSubscription::class, 'plan_id', 'plan_id');
    }

    public function subscriptionRequests(): HasMany
    {
        return $this->hasMany(SubscriptionPlanRequest::class, 'plan_id', 'plan_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    // Checkers & Calculations
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function discountAmount(): float
    {
        $price = (float) $this->plan_price;
        $discountPercent = (float) ($this->plan_discount ?? 0.0);

        return round($price * ($discountPercent / 100), 2);
    }

    public function finalPrice(): float
    {
        $price = (float) $this->plan_price;

        return max(0.0, round($price - $this->discountAmount(), 2));
    }
}
