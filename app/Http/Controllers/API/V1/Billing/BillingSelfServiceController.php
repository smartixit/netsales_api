<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingSubscriptionStatus;
use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Billing\CreateBillingAccountRequest;
use App\Http\Requests\API\V1\Billing\PurchaseSubscriptionRequest;
use App\Http\Requests\API\V1\Billing\StoreBillingAccountContactRequest;
use App\Http\Requests\API\V1\Billing\StorePaymentRequest;
use App\Http\Requests\API\V1\Billing\UpdateBillingAccountContactRequest;
use App\Http\Requests\API\V1\Billing\UpdateBillingAccountRequest;
use App\Http\Resources\API\V1\Billing\BillingAccountContactResource;
use App\Http\Resources\API\V1\Billing\BillingAccountResource;
use App\Http\Resources\API\V1\Billing\BillingCreditNoteResource;
use App\Http\Resources\API\V1\Billing\BillingInvoiceResource;
use App\Http\Resources\API\V1\Billing\BillingPaymentResource;
use App\Http\Resources\API\V1\Billing\BillingPlanResource;
use App\Http\Resources\API\V1\Billing\BillingRefundResource;
use App\Http\Resources\API\V1\Billing\BillingSubscriptionEventResource;
use App\Http\Resources\API\V1\Billing\BillingSubscriptionResource;
use App\Http\Resources\API\V1\Billing\BillingUsageAggregateResource;
use App\Http\Resources\API\V1\Billing\CurrencyResource;
use App\Http\Resources\API\V1\Billing\PaymentMethodResource;
use App\Services\Billing\BillingEntitlementService;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\BillingPlatformReadService;
use App\Services\Billing\BillingSelfService;
use App\Services\Billing\BillingSubscriptionService;
use App\Services\Billing\CurrencyService;
use App\Services\Support\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use OpenApi\Attributes as OA;

class BillingSelfServiceController extends BaseController
{
    public function __construct(
        private BillingSelfService $self,
        private BillingSubscriptionService $subscriptions,
        private BillingPaymentService $payments,
        private BillingPlatformReadService $read,
        private CurrencyService $currencies,
        private BillingEntitlementService $entitlements,
        private FileUploadService $files
    ) {}

    /**
     * Validate bounded cursor collection parameters before passing them to the
     * self-service domain service.
     *
     * @param  array<string, array<int, mixed>>  $extra
     * @return array<string, mixed>
     */
    private function collectionFilters(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'search' => ['nullable', 'string', 'max:120'],
        ], $extra));
    }

    #[OA\Get(
        path: '/api/v1/billing/currencies',
        summary: 'Currencies',
        tags: ['Billing'],
        operationId: 'billingselfservice_currencies',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function currencies(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع العملات المتاحة لفتح حساب فوترة.',
            CurrencyResource::collection($this->currencies->listActive())
        );
    }

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/billing/accounts',
        summary: 'Accounts',
        tags: ['Billing'],
        operationId: 'billingselfservice_accounts',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function accounts(int $networkId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع حسابات الفوترة الخاصة بالشبكة وعملاتها.',
            BillingAccountResource::collection($this->self->accounts($networkId, $this->requireCurrentUser()))
        );
    }

    #[OA\Post(
        path: '/api/v1/networks/{networkId}/billing/accounts',
        summary: 'Create Account',
        tags: ['Billing'],
        operationId: 'billingselfservice_createAccount',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function createAccount(CreateBillingAccountRequest $request, int $networkId): JsonResponse
    {
        try {
            $account = $this->self->createAccount(
                $networkId,
                (int) $request->input('currency_id'),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم إنشاء حساب الفوترة بالعملة المحددة.', new BillingAccountResource($account), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}',
        summary: 'Account',
        tags: ['Billing'],
        operationId: 'billingselfservice_account',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function account(int $billingAccountId): JsonResponse
    {
        return $this->successResponse('تم استرجاع ملف حساب الفوترة وجهات الاتصال.', new BillingAccountResource($this->self->account($billingAccountId, $this->requireCurrentUser())));
    }

    #[OA\Put(
        path: '/api/v1/billing/accounts/{billingAccountId}',
        summary: 'Update Account',
        tags: ['Billing'],
        operationId: 'billingselfservice_updateAccount',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function updateAccount(UpdateBillingAccountRequest $request, int $billingAccountId): JsonResponse
    {
        try {
            $account = $this->self->updateAccount($billingAccountId, $request->validated(), $this->requireCurrentUser());

            return $this->successResponse('تم تحديث بيانات حساب الفوترة.', new BillingAccountResource($account));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/billing/accounts/{billingAccountId}/contacts',
        summary: 'Store Contact',
        tags: ['Billing'],
        operationId: 'billingselfservice_storeContact',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storeContact(StoreBillingAccountContactRequest $request, int $billingAccountId): JsonResponse
    {
        try {
            $contact = $this->self->createContact($billingAccountId, $request->validated(), $this->requireCurrentUser());

            return $this->successResponse('تمت إضافة جهة اتصال لحساب الفوترة.', new BillingAccountContactResource($contact), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Put(
        path: '/api/v1/billing/accounts/{billingAccountId}/contacts/{contactId}',
        summary: 'Update Contact',
        tags: ['Billing'],
        operationId: 'billingselfservice_updateContact',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'contactId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function updateContact(UpdateBillingAccountContactRequest $request, int $billingAccountId, int $contactId): JsonResponse
    {
        try {
            $contact = $this->self->updateContact($billingAccountId, $contactId, $request->validated(), $this->requireCurrentUser());

            return $this->successResponse('تم تحديث جهة اتصال حساب الفوترة.', new BillingAccountContactResource($contact));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Delete(
        path: '/api/v1/billing/accounts/{billingAccountId}/contacts/{contactId}',
        summary: 'Destroy Contact',
        tags: ['Billing'],
        operationId: 'billingselfservice_destroyContact',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'contactId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function destroyContact(int $billingAccountId, int $contactId): JsonResponse
    {
        try {
            $this->self->deleteContact($billingAccountId, $contactId, $this->requireCurrentUser());

            return $this->successResponse('تم حذف جهة اتصال حساب الفوترة.', null);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/entitlements',
        summary: 'Entitlements',
        tags: ['Billing'],
        operationId: 'billingselfservice_entitlements',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'features', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function entitlements(int $billingAccountId): JsonResponse
    {
        $account = $this->self->account($billingAccountId, $this->requireCurrentUser());
        $subscription = $this->entitlements->previewSubscriptionForAccount((int) $account->billing_account_id);

        if (! $subscription) {
            return $this->successResponse('لا توجد استحقاقات تشغيلية فعالة لحساب الفوترة حالياً.', [
                'subscription' => null,
                'trial' => null,
                'features' => [],
            ]);
        }

        $subscription->loadMissing(['planVersion.features', 'currency', 'trialRedemption.policyVersion', 'trialRedemption.adjustments']);
        $status = $subscription->status?->value;
        $canUseFeatures = in_array($status, ['ACTIVE', 'TRIALING', 'PAST_DUE'], true);
        $features = $canUseFeatures ? $subscription->planVersion->features
            ->map(fn ($feature): array => [
                'feature_key' => $feature->feature_key,
                'name' => $feature->name,
                'feature_type' => $feature->feature_type?->value,
                'configured_value' => $this->entitlements->featureValue($subscription, $feature->feature_key),
                'unit' => $feature->unit,
                'aggregation' => $feature->aggregation,
                'reset_period' => $feature->reset_period,
                'overage_behavior' => $feature->overage_behavior,
                'available' => $this->entitlements->allows($subscription, $feature->feature_key),
            ])->values() : collect();

        $trial = (array) $subscription->trial_policy_snapshot;
        $redemption = $subscription->trialRedemption;

        return $this->successResponse('تم استرجاع حالة الاشتراك والاستحقاقات دون بدء أي تجربة معلقة.', [
            'subscription' => new BillingSubscriptionResource($subscription),
            'trial' => $trial !== [] || $redemption ? [
                'status' => $redemption?->status,
                'trial_redemption_id' => $redemption?->trial_redemption_id,
                'redemption_scope' => $redemption?->redemption_scope
                    ?? $trial['redemption_scope']
                    ?? null,
                'start' => $subscription->trial_start?->toDateTimeString(),
                'end' => $subscription->trial_end?->toDateTimeString(),
                'start_at' => $subscription->trial_start?->toIso8601String(),
                'end_at' => $subscription->trial_end?->toIso8601String(),
                'server_now' => now('Asia/Aden')->toIso8601String(),
                'duration_value' => $trial['duration_value'] ?? $redemption?->policyVersion?->duration_value,
                'duration_unit' => $trial['duration_unit'] ?? $redemption?->policyVersion?->duration_unit,
                'start_trigger' => $trial['start_trigger'] ?? $redemption?->policyVersion?->start_trigger,
                'eligibility_mode' => $trial['eligibility_mode'] ?? $redemption?->policyVersion?->eligibility_mode,
                'requires_manual_approval' => (bool) (
                    $trial['requires_manual_approval']
                    ?? $redemption?->policyVersion?->requires_manual_approval
                    ?? false
                ),
                'end_behavior' => $trial['end_behavior'] ?? $redemption?->policyVersion?->end_behavior,
                'entitlement_mode' => $trial['entitlement_mode']
                    ?? $redemption?->policyVersion?->entitlement_mode
                    ?? 'FULL_PLAN',
                'allowed_features' => $trial['entitlement_config']['allowed_features'] ?? [],
                'awaiting_first_activation' => $status === 'INCOMPLETE'
                    && ($trial['start_trigger'] ?? $redemption?->policyVersion?->start_trigger) === 'FIRST_ACTIVATION',
                'adjustments' => $redemption?->adjustments->map(static fn ($adjustment): array => [
                    'trial_adjustment_id' => $adjustment->trial_adjustment_id,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'previous_trial_end' => $adjustment->previous_trial_end?->toDateTimeString(),
                    'new_trial_end' => $adjustment->new_trial_end?->toDateTimeString(),
                    'reason' => $adjustment->reason,
                    'occurred_at' => $adjustment->occurred_at?->toDateTimeString(),
                ])->values() ?? [],
            ] : null,
            'features' => $features,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/plans',
        summary: 'Plans',
        tags: ['Billing'],
        operationId: 'billingselfservice_plans',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function plans(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع الخطط والأسعار المتاحة بعملة حساب الفوترة.',
            BillingPlanResource::collection($this->self->plans(
                $billingAccountId,
                $this->collectionFilters($request),
                $this->requireCurrentUser()
            ))
        );
    }

    #[OA\Post(
        path: '/api/v1/billing/accounts/{billingAccountId}/subscriptions',
        summary: 'Purchase',
        tags: ['Billing'],
        operationId: 'billingselfservice_purchase',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function purchase(PurchaseSubscriptionRequest $request, int $billingAccountId): JsonResponse
    {
        try {
            $result = $this->subscriptions->purchase(
                $billingAccountId,
                (int) $request->input('price_id'),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم إنشاء طلب الاشتراك والفاتورة. يبقى الاشتراك غير مكتمل حتى اعتماد سداد الفاتورة.', [
                'subscription' => new BillingSubscriptionResource($result['subscription']),
                'invoice' => $result['invoice'] ? new BillingInvoiceResource($result['invoice']) : null,
            ], 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/subscriptions',
        summary: 'Subscriptions',
        tags: ['Billing'],
        operationId: 'billingselfservice_subscriptions',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function subscriptions(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع اشتراكات حساب الفوترة.',
            BillingSubscriptionResource::collection($this->self->subscriptions(
                $billingAccountId,
                $this->collectionFilters($request, ['status' => ['nullable', Rule::enum(BillingSubscriptionStatus::class)]]),
                $this->requireCurrentUser()
            ))
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/invoices',
        summary: 'Invoices',
        tags: ['Billing'],
        operationId: 'billingselfservice_invoices',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function invoices(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع فواتير حساب الفوترة.',
            BillingInvoiceResource::collection($this->self->invoices(
                $billingAccountId,
                $this->collectionFilters($request, ['status' => ['nullable', Rule::enum(BillingInvoiceStatus::class)]]),
                $this->requireCurrentUser()
            ))
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/invoices/{invoiceId}',
        summary: 'Invoice',
        tags: ['Billing'],
        operationId: 'billingselfservice_invoice',
        parameters: [new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function invoice(int $invoiceId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع تفاصيل الفاتورة.',
            new BillingInvoiceResource($this->self->invoice($invoiceId, $this->requireCurrentUser()))
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/payments',
        summary: 'Payments',
        tags: ['Billing'],
        operationId: 'billingselfservice_payments',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function payments(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع مدفوعات حساب الفوترة.',
            BillingPaymentResource::collection($this->self->payments(
                $billingAccountId,
                $this->collectionFilters($request, ['status' => ['nullable', Rule::enum(BillingPaymentStatus::class)]]),
                $this->requireCurrentUser()
            ))
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/statement',
        summary: 'Statement',
        tags: ['Billing'],
        operationId: 'billingselfservice_statement',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function statement(Request $request, int $billingAccountId): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,currency_id'],
        ]);

        return $this->successResponse(
            'تم إنشاء كشف نشاط حساب الفوترة للفترة المحددة وبحسب العملة.',
            $this->self->statement(
                $billingAccountId,
                $filters,
                $this->requireCurrentUser()
            )
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/credit-notes',
        summary: 'Credit Notes',
        tags: ['Billing'],
        operationId: 'billingselfservice_creditNotes',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function creditNotes(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع الإشعارات الدائنة لحساب الفوترة.',
            BillingCreditNoteResource::collection($this->self->creditNotes(
                $billingAccountId,
                $this->collectionFilters($request, ['status' => ['nullable', Rule::in(['ISSUED', 'PARTIALLY_REFUNDED', 'REFUNDED'])]]),
                $this->requireCurrentUser()
            ))
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/refunds',
        summary: 'Refunds',
        tags: ['Billing'],
        operationId: 'billingselfservice_refunds',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function refunds(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع عمليات الاسترداد لحساب الفوترة.',
            BillingRefundResource::collection($this->self->refunds(
                $billingAccountId,
                $this->collectionFilters($request, ['status' => ['nullable', Rule::in(['PENDING', 'COMPLETED'])]]),
                $this->requireCurrentUser()
            ))
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/subscriptions/{subscriptionId}',
        summary: 'Subscription',
        tags: ['Billing'],
        operationId: 'billingselfservice_subscription',
        parameters: [new OA\Parameter(name: 'subscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function subscription(int $subscriptionId): JsonResponse
    {
        $actor = $this->requireCurrentUser();
        $subscription = $this->self->subscription($subscriptionId, $actor);

        return $this->successResponse('تم استرجاع تفاصيل الاشتراك وتجربته وأول صفحة من سجل أحداثه.', [
            'subscription' => new BillingSubscriptionResource($subscription),
            'events' => BillingSubscriptionEventResource::collection(
                $this->self->subscriptionEvents($subscriptionId, ['per_page' => 30], $actor)
            ),
            'invoices' => BillingInvoiceResource::collection(
                $subscription->invoices()
                    ->with(['currency', 'lines.currency'])
                    ->orderByDesc('invoice_id')
                    ->cursorPaginate(25)
            ),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/billing/subscriptions/{subscriptionId}/usage',
        summary: 'Usage',
        tags: ['Billing'],
        operationId: 'billingselfservice_usage',
        parameters: [new OA\Parameter(name: 'subscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'cursor', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function usage(Request $request, int $subscriptionId): JsonResponse
    {
        $filters = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);

        return $this->successResponse(
            'تم استرجاع تجميعات استخدام الاشتراك حسب العداد والفترة.',
            BillingUsageAggregateResource::collection($this->self->usage($subscriptionId, $filters, $this->requireCurrentUser()))
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/subscriptions/{subscriptionId}/events',
        summary: 'Subscription Events',
        tags: ['Billing'],
        operationId: 'billingselfservice_subscriptionEvents',
        parameters: [new OA\Parameter(name: 'subscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'cursor', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function subscriptionEvents(Request $request, int $subscriptionId): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);

        return $this->successResponse(
            'تم استرجاع سجل دورة الاشتراك.',
            BillingSubscriptionEventResource::collection(
                $this->self->subscriptionEvents(
                    $subscriptionId,
                    $filters,
                    $this->requireCurrentUser()
                )
            )
        );
    }

    #[OA\Get(
        path: '/api/v1/billing/accounts/{billingAccountId}/payment-methods',
        summary: 'Payment Methods',
        tags: ['Billing'],
        operationId: 'billingselfservice_paymentMethods',
        parameters: [new OA\Parameter(name: 'billingAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function paymentMethods(int $billingAccountId): JsonResponse
    {
        $account = $this->self->account($billingAccountId, $this->requireCurrentUser());

        return $this->successResponse(
            'تم استرجاع وسائل الدفع المتاحة بعملة حساب الفوترة.',
            PaymentMethodResource::collection($this->read->paymentMethods((int) $account->default_currency_id))
        );
    }

    #[OA\Post(
        path: '/api/v1/billing/invoices/{invoiceId}/payments',
        summary: 'Store Payment',
        tags: ['Billing'],
        operationId: 'billingselfservice_storePayment',
        parameters: [new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storePayment(StorePaymentRequest $request, int $invoiceId): JsonResponse
    {
        $actor = $this->requireCurrentUser();
        $proofPath = null;

        try {
            $this->self->assertInvoiceAccess($invoiceId, $actor);
            $proofData = null;

            if ($request->hasFile('proof')) {
                $file = $request->file('proof');
                $proofPath = $this->files->uploadFile($file, 'billing/payment-proofs');

                $proofData = [
                    'file_path' => $proofPath,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                ];
            }

            $payment = $this->payments->recordManualPayment(
                $invoiceId,
                (int) $request->input('payment_method_id'),
                (int) $request->input('amount_minor'),
                (string) $request->input('idempotency_key'),
                $actor,
                $request->input('external_reference'),
                $proofData
            );

            return $this->successResponse('تم تسجيل طلب الدفع وإحالته للمراجعة. لم يتم تفعيل الاشتراك بعد.', new BillingPaymentResource($payment), 201);
        } catch (ValidationException $exception) {
            if ($proofPath !== null) {
                $this->files->deleteFile($proofPath);
            }

            return $this->validationErrorResponse($exception);
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                $this->files->deleteFile($proofPath);
            }

            throw $exception;
        }
    }

    #[OA\Post(
        path: '/api/v1/billing/subscriptions/{subscriptionId}/cancel',
        summary: 'Cancel Subscription',
        tags: ['Billing'],
        operationId: 'billingselfservice_cancelSubscription',
        parameters: [new OA\Parameter(name: 'subscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', properties: [new OA\Property(property: 'at_period_end', type: 'boolean')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function cancelSubscription(Request $request, int $subscriptionId): JsonResponse
    {
        try {
            $request->validate(['at_period_end' => ['sometimes', 'boolean']]);
            $subscription = $this->subscriptions->cancel(
                $subscriptionId,
                $request->has('at_period_end') ? $request->boolean('at_period_end') : true,
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم حفظ قرار إلغاء الاشتراك.', new BillingSubscriptionResource($subscription));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }
}
