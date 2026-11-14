<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $trial_adjustment_id
 * @property int $trial_redemption_id
 * @property int $billing_subscription_id
 * @property string $adjustment_type
 * @property \Illuminate\Support\Carbon|null $previous_trial_end
 * @property \Illuminate\Support\Carbon $new_trial_end
 * @property string $reason
 * @property int $actor_id
 * @property \Illuminate\Support\Carbon $occurred_at
 */
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
