<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingTrialPolicyVersion extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_trial_policy_versions';
    protected $primaryKey = 'trial_policy_version_id';
    protected $fillable = ['trial_policy_id', 'version_number', 'status', 'is_enabled', 'duration_value', 'duration_unit', 'start_trigger', 'redemption_scope', 'eligibility_mode', 'requires_manual_approval', 'end_behavior', 'entitlement_mode', 'entitlement_config', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'requires_manual_approval' => 'boolean', 'duration_value' => 'integer', 'version_number' => 'integer', 'entitlement_config' => 'array', 'published_at' => 'datetime'];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(BillingTrialPolicy::class, 'trial_policy_id', 'trial_policy_id');
    }
}
