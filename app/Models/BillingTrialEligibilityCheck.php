<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingTrialEligibilityCheck extends Model
{
    protected $table = 'billing_trial_eligibility_checks';
    protected $primaryKey = 'trial_eligibility_check_id';

    protected $fillable = [
        'network_id',
        'billing_account_id',
        'price_id',
        'plan_id',
        'trial_policy_version_id',
        'checked_by',
        'eligibility_status',
        'reason_code',
        'source',
        'checked_at',
    ];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id', 'billing_account_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(BillingPrice::class, 'price_id', 'price_id');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(BillingTrialPolicyVersion::class, 'trial_policy_version_id', 'trial_policy_version_id');
    }
}
