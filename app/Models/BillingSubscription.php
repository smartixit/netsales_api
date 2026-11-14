<?php

namespace App\Models;

use App\Enums\BillingSubscriptionStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property int $billing_subscription_id
 * @property int $billing_account_id
 * @property int $plan_id
 * @property int $plan_version_id
 * @property int $price_id
 * @property int $quantity
 * @property array<string, mixed>|null $trial_policy_snapshot
 * @property array<string, mixed>|null $dunning_policy_snapshot
 * @property int $currency_id
 * @property \App\Enums\BillingSubscriptionStatus $status
 * @property \Illuminate\Support\Carbon|null $current_period_start
 * @property \Illuminate\Support\Carbon|null $current_period_end
 * @property \Illuminate\Support\Carbon|null $trial_start
 * @property \Illuminate\Support\Carbon|null $trial_end
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property \Illuminate\Support\Carbon|null $paused_at
 * @property \Illuminate\Support\Carbon|null $grace_period_ends_at
 * @property \Illuminate\Support\Carbon|null $next_billing_at
 * @property array<string, mixed>|null $metadata
 * @property-read BillingAccount $billingAccount
 * @property-read BillingPlan $plan
 * @property-read BillingPlanVersion $planVersion
 * @property-read BillingPrice $price
 * @property-read Currency $currency
 */
class BillingSubscription extends Model
{
    use ExactDateSerialization, SoftDeletes;

    protected $table = 'billing_subscriptions';
    protected $primaryKey = 'billing_subscription_id';

    protected $fillable = [
        'billing_account_id', 'plan_id', 'plan_version_id', 'price_id', 'currency_id', 'trial_policy_version_id', 'dunning_policy_version_id', 'trial_policy_snapshot', 'dunning_policy_snapshot',
        'status', 'collection_method', 'quantity', 'current_period_start', 'current_period_end',
        'trial_start', 'trial_end', 'cancel_at_period_end', 'canceled_at', 'ended_at',
        'paused_at', 'grace_period_ends_at', 'terms_snapshot', 'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => BillingSubscriptionStatus::class,
            'quantity' => 'integer',
            'cancel_at_period_end' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_start' => 'datetime',
            'trial_end' => 'datetime',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
            'paused_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'terms_snapshot' => 'array',
            'trial_policy_snapshot' => 'array',
            'dunning_policy_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $subscription): void {
            $allowed = [
                'status', 'current_period_start', 'current_period_end', 'trial_start', 'trial_end',
                'cancel_at_period_end', 'canceled_at', 'ended_at', 'paused_at',
                'grace_period_ends_at', 'metadata', 'updated_at',
            ];

            if (array_diff(array_keys($subscription->getDirty()), $allowed) !== []) {
                throw new LogicException('الهوية التجارية وشروط الاشتراك وعملته غير قابلة للتعديل؛ استخدم دورة تغيير خطة مستقلة.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('سجلات الاشتراك لا تحذف؛ استخدم الإلغاء أو الانتهاء للمحافظة على سجل التدقيق.');
        });
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id', 'billing_account_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'plan_id', 'plan_id');
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(BillingPlanVersion::class, 'plan_version_id', 'plan_version_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(BillingPrice::class, 'price_id', 'price_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id');
    }

    public function trialPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(BillingTrialPolicyVersion::class, 'trial_policy_version_id', 'trial_policy_version_id');
    }

    public function dunningPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(BillingDunningPolicyVersion::class, 'dunning_policy_version_id', 'dunning_policy_version_id');
    }

    public function trialRedemption(): HasOne
    {
        return $this->hasOne(BillingTrialRedemption::class, 'billing_subscription_id', 'billing_subscription_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingSubscriptionItem::class, 'billing_subscription_id', 'billing_subscription_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BillingSubscriptionEvent::class, 'billing_subscription_id', 'billing_subscription_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class, 'billing_subscription_id', 'billing_subscription_id');
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(BillingUsageEvent::class, 'billing_subscription_id', 'billing_subscription_id');
    }

    public function scopeForAccount(Builder $query, int $id): Builder
    {
        return $query->where('billing_account_id', $id);
    }
}
