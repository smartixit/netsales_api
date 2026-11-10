<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Services\Billing\BillingPolicyManagementService;
use App\Models\{BillingDunningPolicy, BillingTrialPolicy, BillingTrialRedemption};
use App\Enums\BillingTrialRedemptionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingPolicyController extends BaseController
{
    public function __construct(private BillingPolicyManagementService $policies) {}


    public function trialPolicies(Request $request): JsonResponse
    {
        $query = BillingTrialPolicy::query()->with('currentVersion')->orderBy('trial_policy_id');
        if ($request->filled('search')) {
            $term = trim((string) $request->query('search'));
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('policy_key', 'like', "%{$term}%"));
        }
        return $this->successResponse('تم استرجاع سياسات التجربة.', $query->cursorPaginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function dunningPolicies(Request $request): JsonResponse
    {
        $query = BillingDunningPolicy::query()->with('currentVersion')->orderBy('dunning_policy_id');
        if ($request->filled('search')) {
            $term = trim((string) $request->query('search'));
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('policy_key', 'like', "%{$term}%"));
        }
        return $this->successResponse('تم استرجاع سياسات التحصيل.', $query->cursorPaginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function trialPolicyVersions(Request $request, int $policyId): JsonResponse
    {
        $policy = BillingTrialPolicy::query()->findOrFail($policyId);
        $versions = $policy->versions()
            ->with('policy')
            ->orderByDesc('version_number')
            ->orderByDesc('trial_policy_version_id')
            ->cursorPaginate(min(max($request->integer('per_page', 20), 1), 100));

        return $this->successResponse(
            'تم استرجاع إصدارات سياسة التجربة.',
            \\App\\Http\\Resources\\API\\V1\\Billing\\BillingTrialPolicyVersionResource::collection($versions)
        );
    }

    public function dunningPolicyVersions(Request $request, int $policyId): JsonResponse
    {
        $policy = BillingDunningPolicy::query()->findOrFail($policyId);
        $versions = $policy->versions()
            ->with('policy')
            ->orderByDesc('version_number')
            ->orderByDesc('dunning_policy_version_id')
            ->cursorPaginate(min(max($request->integer('per_page', 20), 1), 100));

        return $this->successResponse(
            'تم استرجاع إصدارات سياسة التحصيل.',
            \\App\\Http\\Resources\\API\\V1\\Billing\\BillingDunningPolicyVersionResource::collection($versions)
        );
    }

    public function pendingRedemptions(Request $request): JsonResponse
    {
        $query = BillingTrialRedemption::query()
            ->with(['policy', 'policyVersion', 'subscription.billingAccount.network'])
            ->where('status', BillingTrialRedemptionStatus::PENDING_APPROVAL->value)
            ->orderBy('trial_redemption_id');
        return $this->successResponse('تم استرجاع طلبات التجربة المعلقة.', $query->cursorPaginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function approveRedemption(int $redemptionId): JsonResponse
    {
        $subscription = $this->policies->approveTrialRedemption($redemptionId, $this->requireCurrentUser());
        return $this->successResponse('تم اعتماد طلب التجربة.', $subscription);
    }

    public function rejectRedemption(Request $request, int $redemptionId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $subscription = $this->policies->rejectTrialRedemption($redemptionId, $data['reason'], $this->requireCurrentUser());
        return $this->successResponse('تم رفض طلب التجربة وإغلاق الاشتراك المعلق.', $subscription);
    }

    public function adjustTrial(Request $request, int $subscriptionId): JsonResponse
    {
        $data = $request->validate([
            'adjustment_type' => ['required', Rule::in(['EXTEND', 'SHORTEN', 'END_EARLY'])],
            'days' => ['required_unless:adjustment_type,END_EARLY', 'nullable', 'integer', 'between:1,365'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $subscription = $this->policies->adjustTrial(
            $subscriptionId,
            $data['adjustment_type'],
            isset($data['days']) ? (int) $data['days'] : null,
            $data['reason'],
            $this->requireCurrentUser()
        );
        return $this->successResponse('تم تعديل فترة التجربة وتسجيل سبب التعديل.', $subscription);
    }

    public function storeTrial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'policy_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:billing_trial_policies,policy_key'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_enabled' => ['required', 'boolean'],
            'duration_value' => ['required', 'integer', 'between:0,3650'],
            'duration_unit' => ['required', Rule::in(['HOUR', 'DAY', 'WEEK', 'MONTH'])],
            'start_trigger' => ['required', Rule::in(['PURCHASE', 'FIRST_ACTIVATION'])],
            'redemption_scope' => ['required', Rule::in(['NETWORK', 'BILLING_ACCOUNT', 'PRODUCT'])],
            'eligibility_mode' => ['required', Rule::in(['ANY', 'NEW_NETWORK', 'NEW_BILLING_ACCOUNT'])],
            'requires_manual_approval' => ['sometimes', 'boolean'],
            'end_behavior' => ['required', Rule::in(['CREATE_INVOICE', 'EXPIRE'])],
            'entitlement_mode' => ['required', Rule::in(['FULL_PLAN', 'RESTRICTED', 'NONE'])],
            'entitlement_config' => ['nullable', 'array'],
        ]);
        $version = $this->policies->createTrialPolicy($data, $this->requireCurrentUser());
        return $this->successResponse('تم إنشاء سياسة التجربة كمسودة.', $version, 201);
    }

    public function storeDunning(Request $request): JsonResponse
    {
        $data = $request->validate([
            'policy_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:billing_dunning_policies,policy_key'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'grace_period_days' => ['required', 'integer', 'between:0,90'],
            'auto_suspend_after_grace' => ['required', 'boolean'],
            'reminder_schedule_days' => ['required', 'array', 'max:10'],
            'reminder_schedule_days.*' => ['integer', 'between:0,90'],
            'pre_renewal_alert_days' => ['required', 'array', 'max:10'],
            'pre_renewal_alert_days.*' => ['integer', 'between:0,90'],
        ]);
        $version = $this->policies->createDunningPolicy($data, $this->requireCurrentUser());
        return $this->successResponse('تم إنشاء سياسة التحصيل كمسودة.', $version, 201);
    }

    public function storeTrialVersion(Request $request, int $policyId): JsonResponse
    {
        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'duration_value' => ['required', 'integer', 'between:0,3650'],
            'duration_unit' => ['required', Rule::in(['HOUR', 'DAY', 'WEEK', 'MONTH'])],
            'start_trigger' => ['required', Rule::in(['PURCHASE', 'FIRST_ACTIVATION'])],
            'redemption_scope' => ['required', Rule::in(['NETWORK', 'BILLING_ACCOUNT', 'PRODUCT'])],
            'eligibility_mode' => ['required', Rule::in(['ANY', 'NEW_NETWORK', 'NEW_BILLING_ACCOUNT'])],
            'requires_manual_approval' => ['sometimes', 'boolean'],
            'end_behavior' => ['required', Rule::in(['CREATE_INVOICE', 'EXPIRE'])],
            'entitlement_mode' => ['required', Rule::in(['FULL_PLAN', 'RESTRICTED', 'NONE'])],
            'entitlement_config' => ['nullable', 'array'],
        ]);
        return $this->successResponse('تم إنشاء إصدار مسودة لسياسة التجربة.', $this->policies->createTrialVersion($policyId, $data, $this->requireCurrentUser()), 201);
    }

    public function storeDunningVersion(Request $request, int $policyId): JsonResponse
    {
        $data = $request->validate([
            'grace_period_days' => ['required', 'integer', 'between:0,90'],
            'auto_suspend_after_grace' => ['required', 'boolean'],
            'reminder_schedule_days' => ['required', 'array', 'max:10'],
            'reminder_schedule_days.*' => ['integer', 'between:0,90'],
            'pre_renewal_alert_days' => ['required', 'array', 'max:10'],
            'pre_renewal_alert_days.*' => ['integer', 'between:0,90'],
        ]);
        return $this->successResponse('تم إنشاء إصدار مسودة لسياسة التحصيل.', $this->policies->createDunningVersion($policyId, $data, $this->requireCurrentUser()), 201);
    }

    public function publishTrial(int $versionId): JsonResponse
    {
        return $this->successResponse('تم نشر إصدار سياسة التجربة.', $this->policies->publishTrialVersion($versionId, $this->requireCurrentUser()));
    }

    public function publishDunning(int $versionId): JsonResponse
    {
        return $this->successResponse('تم نشر إصدار سياسة التحصيل.', $this->policies->publishDunningVersion($versionId, $this->requireCurrentUser()));
    }

    public function assignPlanVersion(Request $request, int $versionId): JsonResponse
    {
        $data = $request->validate([
            'trial_policy_version_id' => ['nullable', 'integer', 'exists:billing_trial_policy_versions,trial_policy_version_id'],
            'dunning_policy_version_id' => ['nullable', 'integer', 'exists:billing_dunning_policy_versions,dunning_policy_version_id'],
        ]);
        return $this->successResponse('تم ربط سياسات الاشتراك بنسخة الخطة.', $this->policies->assignToPlanVersion($versionId, $data, $this->requireCurrentUser()));
    }

    public function assignPrice(Request $request, int $priceId): JsonResponse
    {
        $data = $request->validate([
            'trial_policy_version_id' => ['nullable', 'integer', 'exists:billing_trial_policy_versions,trial_policy_version_id'],
            'dunning_policy_version_id' => ['nullable', 'integer', 'exists:billing_dunning_policy_versions,dunning_policy_version_id'],
        ]);
        return $this->successResponse('تم ربط سياسات الاشتراك بالسعر.', $this->policies->assignToPrice($priceId, $data, $this->requireCurrentUser()));
    }
}
