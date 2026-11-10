<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingTrialAdjustment extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_trial_adjustments';
    protected $primaryKey = 'trial_adjustment_id';
    protected $fillable = ['trial_redemption_id', 'billing_subscription_id', 'adjustment_type', 'previous_trial_end', 'new_trial_end', 'reason', 'actor_id', 'occurred_at', 'metadata'];

    protected function casts(): array
    {
        return ['previous_trial_end' => 'datetime', 'new_trial_end' => 'datetime', 'occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(BillingTrialRedemption::class, 'trial_redemption_id', 'trial_redemption_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id', 'billing_subscription_id');
    }
}
