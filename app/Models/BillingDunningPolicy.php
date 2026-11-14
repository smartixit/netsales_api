<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $dunning_policy_id
 * @property string $policy_key
 * @property string $name
 * @property int|null $current_version_id
 * @property bool $is_active
 * @property-read BillingDunningPolicyVersion|null $currentVersion
 */
class BillingDunningPolicy extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_dunning_policies';
    protected $primaryKey = 'dunning_policy_id';
    protected $fillable = ['policy_key', 'name', 'description', 'current_version_id', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<BillingDunningPolicyVersion, BillingDunningPolicy> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(BillingDunningPolicyVersion::class, 'current_version_id', 'dunning_policy_version_id');
    }

    /** @return HasMany<BillingDunningPolicyVersion, BillingDunningPolicy> */
    public function versions(): HasMany
    {
        return $this->hasMany(BillingDunningPolicyVersion::class, 'dunning_policy_id');
    }
}
