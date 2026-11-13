<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Models\{BillingAccount, BillingPrice, BillingSubscription};
use App\Services\Billing\{BillingAuthorizationService, BillingPolicyResolver};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillingTrialEligibilityController extends BaseController
{
    public function __construct(
        private BillingAuthorizationService $authorization,
        private BillingPolicyResolver $policies
    ) {}

    public function show(Request $request, int $billingAccountId, int $priceId): JsonResponse
    {
        $actor = $this->requireCurrentUser();
        $account = BillingAccount::query()
            ->with('network')
            ->findOrFail($billingAccountId);
        $this->authorization->assertNetworkOwner($actor, (int) $account->network_id);

        if ($account->status !== 'ACTIVE' || ! $account->network?->isActive()) {
            throw ValidationException::withMessages([
                'billing_account_id' => ['حساب الفوترة أو الشبكة غير متاحين للشراء.'],
            ]);
        }

        $price = BillingPrice::query()->with([
            'currency',
            'trialPolicyVersion.policy',
            'dunningPolicyVersion.policy',
            'planVersion.plan.product',
            'planVersion.trialPolicyVersion.policy',
            'planVersion.dunningPolicyVersion.policy',
        ])->findOrFail($priceId);

        if (! $price->is_active
            || ! $price->currency?->is_active
            || $price->planVersion?->status?->value !== 'PUBLISHED'
            || ! $price->planVersion->plan?->is_active
            || ! $price->planVersion->plan?->is_public
            || $price->billing_interval?->value === 'ONE_TIME') {
            throw ValidationException::withMessages([
                'price_id' => ['السعر أو الخطة غير متاحين للاشتراك العام.'],
            ]);
        }

        if ((int) $price->currency_id !== (int) $account->default_currency_id) {
            throw ValidationException::withMessages([
                'price_id' => ['عملة السعر لا تطابق العملة التعاقدية لحساب الفوترة.'],
            ]);
        }

        $trialVersion = $price->trialPolicyVersion
            ?? $price->planVersion->trialPolicyVersion
            ?? $this->policies->defaultTrialPolicy();

        $trialEnabled = $trialVersion
            && $trialVersion->status === 'PUBLISHED'
            && $trialVersion->policy?->is_active
            && $trialVersion->is_enabled
            && (int) $trialVersion->duration_value > 0;

        $eligible = $trialEnabled
            && $this->policies->isEligible($trialVersion, $account, $account->network, $price->planVersion->plan);

        $dunningVersion = $price->dunningPolicyVersion
            ?? $price->planVersion->dunningPolicyVersion
            ?? $this->policies->defaultDunningPolicy();
        $dunningReady = $dunningVersion
            && $dunningVersion->status === 'PUBLISHED'
            && $dunningVersion->policy?->is_active;

        $liveStatuses = ['INCOMPLETE', 'TRIALING', 'ACTIVE', 'PAST_DUE', 'PAUSED', 'SUSPENDED'];
        $existingSubscription = BillingSubscription::query()
            ->whereIn('status', $liveStatuses)
            ->whereHas('billingAccount', fn ($query) => $query->where('network_id', $account->network_id))
            ->exists();

        return $this->successResponse('تم احتساب أهلية التجربة دون حجزها أو تغيير حالة الاشتراك.', [
            'billing_account_id' => (int) $account->billing_account_id,
            'price_id' => (int) $price->price_id,
            'currency' => [
                'code' => $price->currency->code,
                'symbol' => $price->currency->symbol,
                'decimal_places' => (int) $price->currency->decimal_places,
            ],
            'trial' => [
                'eligible' => (bool) $eligible,
                'reason_code' => ! $trialEnabled ? 'TRIAL_NOT_AVAILABLE' : ($eligible ? null : 'TRIAL_ALREADY_REDEEMED'),
                'policy' => $trialEnabled ? $this->policies->snapshot($trialVersion) : null,
            ],
            'purchase_ready' => (bool) $dunningReady && ! $existingSubscription,
            'purchase_blockers' => array_values(array_filter([
                ! $dunningReady ? 'DUNNING_POLICY_UNAVAILABLE' : null,
                $existingSubscription ? 'NETWORK_SUBSCRIPTION_ALREADY_EXISTS' : null,
            ])),
        ]);
    }
}
