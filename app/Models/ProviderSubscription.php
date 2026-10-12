<?php

namespace App\Models;

use App\Enums\ProviderSubscriptionStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderSubscription extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'provider_subscriptions';

    protected $primaryKey = 'subscription_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'plan_id',
        'subscription_start_date',
        'subscription_end_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProviderSubscriptionStatus::class,
            'subscription_start_date' => 'datetime',
            'subscription_end_date' => 'datetime',
        ];
    }

    // Relationships
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(
            SubscriptionPlanFeatureConsumption::class,
            'subscription_id',
            'subscription_id'
        );
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProviderSubscriptionStatus::ACTIVE)
            ->where(function (Builder $q): void {
                $q->whereNull('subscription_end_date')
                    ->orWhere('subscription_end_date', '>', now('Asia/Aden'));
            });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ProviderSubscriptionStatus::EXPIRED)
            ->orWhere(function (Builder $q): void {
                $q->whereNotNull('subscription_end_date')
                    ->where('subscription_end_date', '<=', now('Asia/Aden'));
            });
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    // Checkers & Helpers
    public function isActive(): bool
    {
        if ($this->status !== ProviderSubscriptionStatus::ACTIVE) {
            return false;
        }

        return $this->subscription_end_date === null || now('Asia/Aden')->lt($this->subscription_end_date);
    }

    public function isExpired(): bool
    {
        if ($this->status === ProviderSubscriptionStatus::EXPIRED) {
            return true;
        }

        return $this->subscription_end_date !== null && now('Asia/Aden')->gte($this->subscription_end_date);
    }

    public function daysRemaining(): int
    {
        if ($this->subscription_end_date === null) {
            return PHP_INT_MAX;
        }

        return (int) max(0, now('Asia/Aden')->diffInDays($this->subscription_end_date, false));
    }

    public function canConsumeFeature(int $planFeatureId, int $amount = 1): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $planFeature = $this->plan?->planFeatures()->where('subscription_plan_feature_id', $planFeatureId)->first();
        if (! $planFeature || ! $planFeature->status) {
            return false;
        }

        $limit = (int) $planFeature->plan_feature_value;
        if ($limit === -1) {
            return true;
        }

        $consumed = (int) ($this->consumptions()->where('feature_id', $planFeatureId)->value('consumption_value') ?? 0);

        return ($consumed + $amount) <= $limit;
    }
}
