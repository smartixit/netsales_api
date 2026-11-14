<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @property int $trial_redemption_id
 * @property int $billing_subscription_id
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property-read BillingTrialPolicy $policy
 * @property-read BillingTrialPolicyVersion $policyVersion
 * @property-read BillingSubscription $subscription
 */
class BillingTrialRedemption extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_trial_redemptions';
    protected $primaryKey = 'trial_redemption_id';
    protected $fillable = ['trial_policy_id', 'trial_policy_version_id', 'billing_subscription_id', 'network_id', 'billing_account_id', 'product_id', 'redemption_scope', 'redemption_subject_key', 'status', 'reserved_at', 'started_at', 'completed_at', 'metadata'];

    protected function casts(): array
    {
        return ['reserved_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(BillingTrialPolicy::class, 'trial_policy_id', 'trial_policy_id');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(BillingTrialPolicyVersion::class, 'trial_policy_version_id', 'trial_policy_version_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BillingTrialAdjustment::class, 'trial_redemption_id', 'trial_redemption_id')
            ->orderByDesc('occurred_at')->orderByDesc('trial_adjustment_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id', 'billing_subscription_id');
    }
}
