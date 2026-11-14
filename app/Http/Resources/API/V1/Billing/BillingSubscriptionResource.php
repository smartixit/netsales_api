<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'billing_subscription_id' => $this->billing_subscription_id,
            'billing_account_id' => $this->billing_account_id,
            'billing_account' => $this->whenLoaded('billingAccount', fn () => [
                'billing_account_id' => $this->billingAccount?->billing_account_id,
                'account_number' => $this->billingAccount?->account_number,
                'network_id' => $this->billingAccount?->network_id,
                'network_name' => $this->billingAccount?->network?->network_name,
                'legal_name' => $this->billingAccount?->legal_name,
                'status' => $this->billingAccount?->status,
            ]),
            'plan_id' => $this->plan_id,
            'plan_version_id' => $this->plan_version_id,
            'price_id' => $this->price_id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'collection_method' => $this->collection_method,
            'quantity' => (int) $this->quantity,
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
            'plan' => $this->whenLoaded('plan', fn () => [
                'plan_id' => $this->plan?->plan_id,
                'plan_key' => $this->plan?->plan_key,
                'name' => $this->plan?->name,
            ]),
            'terms_snapshot' => $this->terms_snapshot,
            'current_period_start' => $this->current_period_start?->toDateTimeString(),
            'current_period_end' => $this->current_period_end?->toDateTimeString(),
            'trial_start' => $this->trial_start?->toDateTimeString(),
            'trial_end' => $this->trial_end?->toDateTimeString(),
            'trial_start_at' => $this->trial_start?->toIso8601String(),
            'trial_end_at' => $this->trial_end?->toIso8601String(),
            'server_now' => now('Asia/Aden')->toIso8601String(),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'grace_period_ends_at' => $this->grace_period_ends_at?->toDateTimeString(),
            'trial_policy_snapshot' => $this->trial_policy_snapshot,
            'dunning_policy_snapshot' => $this->dunning_policy_snapshot,
            'trial_redemption' => $this->whenLoaded('trialRedemption', function () {
                if (! $this->trialRedemption) return null;
                $r = $this->trialRedemption;
                return [
                    'trial_redemption_id' => $r->trial_redemption_id,
                    'status' => $r->status,
                    'redemption_scope' => $r->redemption_scope,
                    'reserved_at' => $r->reserved_at?->toDateTimeString(),
                    'started_at' => $r->started_at?->toDateTimeString(),
                    'completed_at' => $r->completed_at?->toDateTimeString(),
                    'metadata' => $r->metadata,
                    'policy_version' => $r->policyVersion ? [
                        'version_number' => (int) $r->policyVersion->version_number,
                        'duration_value' => (int) $r->policyVersion->duration_value,
                        'duration_unit' => $r->policyVersion->duration_unit,
                        'start_trigger' => $r->policyVersion->start_trigger,
                        'requires_manual_approval' => (bool) $r->policyVersion->requires_manual_approval,
                        'end_behavior' => $r->policyVersion->end_behavior,
                        'entitlement_mode' => $r->policyVersion->entitlement_mode,
                    ] : null,
                    'adjustments' => $r->adjustments->map(static fn ($a) => [
                        'trial_adjustment_id' => $a->trial_adjustment_id,
                        'adjustment_type' => $a->adjustment_type,
                        'previous_trial_end' => $a->previous_trial_end?->toDateTimeString(),
                        'new_trial_end' => $a->new_trial_end?->toDateTimeString(),
                        'reason' => $a->reason,
                        'occurred_at' => $a->occurred_at?->toDateTimeString(),
                    ])->values(),
                ];
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
