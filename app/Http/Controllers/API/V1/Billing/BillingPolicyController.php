<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Enums\BillingTrialRedemptionStatus;
use App\Http\Controllers\API\V1\BaseController;
use App\Http\Resources\API\V1\Billing\BillingDunningPolicyVersionResource;
use App\Http\Resources\API\V1\Billing\BillingTrialPolicyVersionResource;
use App\Models\BillingDunningPolicy;
use App\Models\BillingTrialPolicy;
use App\Models\BillingTrialRedemption;
use App\Services\Billing\BillingPolicyManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class BillingPolicyController extends BaseController
{
    public function __construct(private BillingPolicyManagementService $policies) {}

    #[OA\Get(
        path: '/api/v1/platform/billing/policies/trials',
        summary: 'Trial Policies',
        tags: ['Billing'],
        operationId: 'billingpolicy_trialPolicies',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function trialPolicies(Request $request): JsonResponse
    {
        $query = BillingTrialPolicy::query()->with('currentVersion')->orderBy('trial_policy_id');
        if ($request->filled('search')) {
            $term = trim((string) $request->query('search'));
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('policy_key', 'like', "%{$term}%"));
        }

        return $this->successResponse('تم استرجاع سياسات التجربة.', $query->cursorPaginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/policies/dunning',
        summary: 'Dunning Policies',
        tags: ['Billing'],
        operationId: 'billingpolicy_dunningPolicies',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function dunningPolicies(Request $request): JsonResponse
    {
        $query = BillingDunningPolicy::query()->with('currentVersion')->orderBy('dunning_policy_id');
        if ($request->filled('search')) {
            $term = trim((string) $request->query('search'));
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('policy_key', 'like', "%{$term}%"));
        }

        return $this->successResponse('تم استرجاع سياسات التحصيل.', $query->cursorPaginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/policies/trials/{policyId}/versions',
        summary: 'Trial Policy Versions',
        tags: ['Billing'],
        operationId: 'billingpolicy_trialPolicyVersions',
        parameters: [new OA\Parameter(name: 'policyId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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
            BillingTrialPolicyVersionResource::collection($versions)
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/policies/dunning/{policyId}/versions',
        summary: 'Dunning Policy Versions',
        tags: ['Billing'],
        operationId: 'billingpolicy_dunningPolicyVersions',
        parameters: [new OA\Parameter(name: 'policyId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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
            BillingDunningPolicyVersionResource::collection($versions)
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/policies/trial-redemptions/pending',
        summary: 'Pending Redemptions',
        tags: ['Billing'],
        operationId: 'billingpolicy_pendingRedemptions',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function pendingRedemptions(Request $request): JsonResponse
    {
        $query = BillingTrialRedemption::query()
            ->with(['policy', 'policyVersion', 'subscription.billingAccount.network'])
            ->where('status', BillingTrialRedemptionStatus::PENDING_APPROVAL->value)
            ->orderBy('trial_redemption_id');

        return $this->successResponse('تم استرجاع طلبات التجربة المعلقة.', $query->cursorPaginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/trial-redemptions/{redemptionId}/approve',
        summary: 'Approve Redemption',
        tags: ['Billing'],
        operationId: 'billingpolicy_approveRedemption',
        parameters: [new OA\Parameter(name: 'redemptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function approveRedemption(int $redemptionId): JsonResponse
    {
        $subscription = $this->policies->approveTrialRedemption($redemptionId, $this->requireCurrentUser());

        return $this->successResponse('تم اعتماد طلب التجربة.', $subscription);
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/trial-redemptions/{redemptionId}/reject',
        summary: 'Reject Redemption',
        tags: ['Billing'],
        operationId: 'billingpolicy_rejectRedemption',
        parameters: [new OA\Parameter(name: 'redemptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function rejectRedemption(Request $request, int $redemptionId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $subscription = $this->policies->rejectTrialRedemption($redemptionId, $data['reason'], $this->requireCurrentUser());

        return $this->successResponse('تم رفض طلب التجربة وإغلاق الاشتراك المعلق.', $subscription);
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/subscriptions/{subscriptionId}/trial-adjustments',
        summary: 'Adjust Trial',
        tags: ['Billing'],
        operationId: 'billingpolicy_adjustTrial',
        parameters: [new OA\Parameter(name: 'subscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['adjustment_type', 'days', 'reason'], properties: [new OA\Property(property: 'adjustment_type', type: 'string'), new OA\Property(property: 'days', type: 'integer'), new OA\Property(property: 'reason', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/trials',
        summary: 'Store Trial',
        tags: ['Billing'],
        operationId: 'billingpolicy_storeTrial',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['policy_key', 'name', 'is_enabled', 'duration_value', 'duration_unit', 'start_trigger', 'redemption_scope', 'eligibility_mode', 'end_behavior', 'entitlement_mode'], properties: [new OA\Property(property: 'policy_key', type: 'string'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'description', type: 'string'), new OA\Property(property: 'is_enabled', type: 'boolean'), new OA\Property(property: 'duration_value', type: 'integer'), new OA\Property(property: 'duration_unit', type: 'string'), new OA\Property(property: 'start_trigger', type: 'string'), new OA\Property(property: 'redemption_scope', type: 'string'), new OA\Property(property: 'eligibility_mode', type: 'string'), new OA\Property(property: 'requires_manual_approval', type: 'boolean'), new OA\Property(property: 'end_behavior', type: 'string'), new OA\Property(property: 'entitlement_mode', type: 'string'), new OA\Property(property: 'entitlement_config', type: 'array', items: new OA\Items(type: 'string'))]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/dunning',
        summary: 'Store Dunning',
        tags: ['Billing'],
        operationId: 'billingpolicy_storeDunning',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['policy_key', 'name', 'grace_period_days', 'auto_suspend_after_grace', 'reminder_schedule_days', 'pre_renewal_alert_days'], properties: [new OA\Property(property: 'policy_key', type: 'string'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'description', type: 'string'), new OA\Property(property: 'grace_period_days', type: 'integer'), new OA\Property(property: 'auto_suspend_after_grace', type: 'boolean'), new OA\Property(property: 'reminder_schedule_days', type: 'array', items: new OA\Items(type: 'string')), new OA\Property(property: 'pre_renewal_alert_days', type: 'array', items: new OA\Items(type: 'string'))]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/trials/{policyId}/versions',
        summary: 'Store Trial Version',
        tags: ['Billing'],
        operationId: 'billingpolicy_storeTrialVersion',
        parameters: [new OA\Parameter(name: 'policyId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['is_enabled', 'duration_value', 'duration_unit', 'start_trigger', 'redemption_scope', 'eligibility_mode', 'end_behavior', 'entitlement_mode'], properties: [new OA\Property(property: 'is_enabled', type: 'boolean'), new OA\Property(property: 'duration_value', type: 'integer'), new OA\Property(property: 'duration_unit', type: 'string'), new OA\Property(property: 'start_trigger', type: 'string'), new OA\Property(property: 'redemption_scope', type: 'string'), new OA\Property(property: 'eligibility_mode', type: 'string'), new OA\Property(property: 'requires_manual_approval', type: 'boolean'), new OA\Property(property: 'end_behavior', type: 'string'), new OA\Property(property: 'entitlement_mode', type: 'string'), new OA\Property(property: 'entitlement_config', type: 'array', items: new OA\Items(type: 'string'))]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/dunning/{policyId}/versions',
        summary: 'Store Dunning Version',
        tags: ['Billing'],
        operationId: 'billingpolicy_storeDunningVersion',
        parameters: [new OA\Parameter(name: 'policyId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['grace_period_days', 'auto_suspend_after_grace', 'reminder_schedule_days', 'pre_renewal_alert_days'], properties: [new OA\Property(property: 'grace_period_days', type: 'integer'), new OA\Property(property: 'auto_suspend_after_grace', type: 'boolean'), new OA\Property(property: 'reminder_schedule_days', type: 'array', items: new OA\Items(type: 'string')), new OA\Property(property: 'pre_renewal_alert_days', type: 'array', items: new OA\Items(type: 'string'))]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/trial-versions/{versionId}/publish',
        summary: 'Publish Trial',
        tags: ['Billing'],
        operationId: 'billingpolicy_publishTrial',
        parameters: [new OA\Parameter(name: 'versionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function publishTrial(int $versionId): JsonResponse
    {
        return $this->successResponse('تم نشر إصدار سياسة التجربة.', $this->policies->publishTrialVersion($versionId, $this->requireCurrentUser()));
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/policies/dunning-versions/{versionId}/publish',
        summary: 'Publish Dunning',
        tags: ['Billing'],
        operationId: 'billingpolicy_publishDunning',
        parameters: [new OA\Parameter(name: 'versionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function publishDunning(int $versionId): JsonResponse
    {
        return $this->successResponse('تم نشر إصدار سياسة التحصيل.', $this->policies->publishDunningVersion($versionId, $this->requireCurrentUser()));
    }

    #[OA\Put(
        path: '/api/v1/platform/billing/policies/plan-versions/{versionId}',
        summary: 'Assign Plan Version',
        tags: ['Billing'],
        operationId: 'billingpolicy_assignPlanVersion',
        parameters: [new OA\Parameter(name: 'versionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', properties: [new OA\Property(property: 'trial_policy_version_id', type: 'integer'), new OA\Property(property: 'dunning_policy_version_id', type: 'integer')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function assignPlanVersion(Request $request, int $versionId): JsonResponse
    {
        $data = $request->validate([
            'trial_policy_version_id' => ['nullable', 'integer', 'exists:billing_trial_policy_versions,trial_policy_version_id'],
            'dunning_policy_version_id' => ['nullable', 'integer', 'exists:billing_dunning_policy_versions,dunning_policy_version_id'],
        ]);

        return $this->successResponse('تم ربط سياسات الاشتراك بنسخة الخطة.', $this->policies->assignToPlanVersion($versionId, $data, $this->requireCurrentUser()));
    }

    #[OA\Put(
        path: '/api/v1/platform/billing/policies/prices/{priceId}',
        summary: 'Assign Price',
        tags: ['Billing'],
        operationId: 'billingpolicy_assignPrice',
        parameters: [new OA\Parameter(name: 'priceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', properties: [new OA\Property(property: 'trial_policy_version_id', type: 'integer'), new OA\Property(property: 'dunning_policy_version_id', type: 'integer')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function assignPrice(Request $request, int $priceId): JsonResponse
    {
        $data = $request->validate([
            'trial_policy_version_id' => ['nullable', 'integer', 'exists:billing_trial_policy_versions,trial_policy_version_id'],
            'dunning_policy_version_id' => ['nullable', 'integer', 'exists:billing_dunning_policy_versions,dunning_policy_version_id'],
        ]);

        return $this->successResponse('تم ربط سياسات الاشتراك بالسعر.', $this->policies->assignToPrice($priceId, $data, $this->requireCurrentUser()));
    }
}
