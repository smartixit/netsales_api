<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingDunningPolicyVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dunning_policy_version_id' => $this->dunning_policy_version_id,
            'dunning_policy_id' => $this->dunning_policy_id,
            'policy_key' => $this->policy?->policy_key,
            'version_number' => (int) $this->version_number,
            'status' => $this->status,
            'grace_period_days' => (int) $this->grace_period_days,
            'auto_suspend_after_grace' => (bool) $this->auto_suspend_after_grace,
            'reminder_schedule_days' => $this->reminder_schedule_days,
            'pre_renewal_alert_days' => $this->pre_renewal_alert_days,
            'published_at' => $this->published_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
