<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Services\Billing\BillingPolicyManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingPolicyController extends BaseController
{
    public function __construct(private BillingPolicyManagementService $policies) {}

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
