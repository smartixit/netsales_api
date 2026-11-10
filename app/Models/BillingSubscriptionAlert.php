<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSubscriptionAlert extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_subscription_alerts';
    protected $primaryKey = 'subscription_alert_id';
    protected $fillable = ['billing_subscription_id','dunning_policy_version_id','period_end','alert_days','scheduled_at','status','queued_at','message_uuid','result_message'];

    protected function casts(): array
    {
        return ['period_end' => 'datetime', 'scheduled_at' => 'datetime', 'queued_at' => 'datetime', 'alert_days' => 'integer'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id', 'billing_subscription_id');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(BillingDunningPolicyVersion::class, 'dunning_policy_version_id', 'dunning_policy_version_id');
    }
}
