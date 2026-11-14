<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $trial_policy_id
 * @property string $policy_key
 * @property string $name
 * @property int|null $current_version_id
 * @property bool $is_active
 * @property-read BillingTrialPolicyVersion|null $currentVersion
 */
class BillingTrialPolicy extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_trial_policies';
    protected $primaryKey = 'trial_policy_id';
    protected $fillable = ['policy_key', 'name', 'description', 'current_version_id', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<BillingTrialPolicyVersion, BillingTrialPolicy> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(BillingTrialPolicyVersion::class, 'current_version_id', 'trial_policy_version_id');
    }

    /** @return HasMany<BillingTrialPolicyVersion, BillingTrialPolicy> */
    public function versions(): HasMany
    {
        return $this->hasMany(BillingTrialPolicyVersion::class, 'trial_policy_id');
    }
}
