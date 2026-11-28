<?php

namespace Tests\Concerns;

use App\Models\BillingDunningPolicy;
use App\Models\BillingDunningPolicyVersion;
use App\Models\User;

trait CreatesBillingDunningPolicyDraft
{
    protected function createBillingDunningPolicyDraft(User $actor): BillingDunningPolicyVersion
    {
        $policy = BillingDunningPolicy::query()
            ->where('policy_key', 'platform-default')
            ->firstOrFail();

        $nextVersion = (int) $policy->versions()->max('version_number') + 1;

        return $policy->versions()->create([
            'version_number' => $nextVersion,
            'status' => 'DRAFT',
            'grace_period_days' => 7,
            'auto_suspend_after_grace' => true,
            'reminder_schedule_days' => [0, 2, 5, 7],
            'pre_renewal_alert_days' => [7, 3, 1],
            'created_by' => $actor->user_id,
        ]);
    }
}
