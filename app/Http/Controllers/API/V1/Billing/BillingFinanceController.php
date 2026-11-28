<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Billing\RejectPaymentRequest;
use App\Http\Requests\API\V1\Billing\StoreCreditNoteRequest;
use App\Http\Requests\API\V1\Billing\StoreCurrencyConversionRequest;
use App\Http\Requests\API\V1\Billing\StoreExchangeRateRequest;
use App\Http\Requests\API\V1\Billing\StoreRefundRequest;
use App\Http\Requests\API\V1\Billing\StoreTreasuryAccountRequest;
use App\Http\Requests\API\V1\Billing\VerifyPaymentRequest;
use App\Http\Resources\API\V1\Billing\BillingAccountResource;
use App\Http\Resources\API\V1\Billing\BillingInvoiceResource;
use App\Http\Resources\API\V1\Billing\BillingPaymentResource;
use App\Http\Resources\API\V1\Billing\BillingSubscriptionResource;
use App\Http\Resources\API\V1\Billing\CurrencyConversionResource;
use App\Http\Resources\API\V1\Billing\CurrencyExchangeRateResource;
use App\Http\Resources\API\V1\Billing\CurrencyResource;
use App\Http\Resources\API\V1\Billing\LedgerJournalEntryResource;
use App\Http\Resources\API\V1\Billing\PaymentMethodResource;
use App\Http\Resources\API\V1\Billing\TreasuryAccountResource;
use App\Models\BillingSubscription;
use App\Services\Billing\BillingAccountService;
use App\Services\Billing\BillingCreditNoteService;
use App\Services\Billing\BillingDashboardService;
use App\Services\Billing\BillingInvoiceService;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\BillingPlatformReadService;
use App\Services\Billing\BillingReconciliationService;
use App\Services\Billing\BillingRefundService;
use App\Services\Billing\BillingSubscriptionService;
use App\Services\Billing\BillingTreasuryService;
use App\Services\Billing\CurrencyExchangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class BillingFinanceController extends BaseController
{
    public function __construct(
        private BillingPlatformReadService $read,
        private BillingTreasuryService $treasury,
        private BillingPaymentService $payments,
        private BillingInvoiceService $invoices,
        private CurrencyExchangeService $exchange,
        private BillingDashboardService $dashboard,
        private BillingRefundService $refunds,
        private BillingCreditNoteService $creditNotes,
        private BillingReconciliationService $reconciliation,
        private BillingAccountService $accountsService,
        private BillingSubscriptionService $subscriptionsService
    ) {}

    #[OA\Get(
        path: '/api/v1/platform/billing/overview',
        summary: 'Overview',
        tags: ['Billing'],
        operationId: 'billingfinance_overview',
        parameters: [new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function overview(Request $request): JsonResponse
    {
        $request->validate([
            'currency_id' => ['nullable', 'integer', 'exists:currencies,currency_id'],
        ]);

        $data = $this->dashboard->overview(
            $request->filled('currency_id') ? (int) $request->input('currency_id') : null
        );
        $data['payment_review_queue'] = BillingPaymentResource::collection($data['payment_review_queue']);

        return $this->successResponse('تم استرجاع ملخص اشتراكات المنصة والتحصيلات حسب العملة.', $data);
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/reconciliation',
        summary: 'Reconciliation',
        tags: ['Billing'],
        operationId: 'billingfinance_reconciliation',
        parameters: [new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function reconciliation(Request $request): JsonResponse
    {
        $request->validate([
            'currency_id' => ['nullable', 'integer', 'exists:currencies,currency_id'],
        ]);

        $result = $this->reconciliation->report(
            $request->filled('currency_id') ? (int) $request->input('currency_id') : null
        );

        return $this->successResponse('تم استرجاع مطابقة الأستاذ العام والدفاتر الفرعية لكل عملة.', $result);
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/accounts',
        summary: 'Accounts',
        tags: ['Billing'],
        operationId: 'billingfinance_accounts',
        parameters: [new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function accounts(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع حسابات الفوترة.',
            BillingAccountResource::collection($this->read->accounts($request->only(['network_id', 'currency_id', 'status', 'per_page', 'all'])))
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/subscriptions',
        summary: 'Subscriptions',
        tags: ['Billing'],
        operationId: 'billingfinance_subscriptions',
        parameters: [new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'billing_account_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function subscriptions(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع اشتراكات المنصة.',
            BillingSubscriptionResource::collection($this->read->subscriptions($request->only([
                'network_id', 'billing_account_id', 'currency_id', 'status', 'search', 'per_page', 'all',
            ])))
        );
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/accounts',
        summary: 'Create Account',
        tags: ['Billing'],
        operationId: 'billingfinance_createAccount',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['network_id', 'currency_id'], properties: [new OA\Property(property: 'network_id', type: 'integer'), new OA\Property(property: 'currency_id', type: 'integer')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function createAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'network_id' => ['required', 'integer', 'exists:networks,network_id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,currency_id'],
        ]);

        $account = $this->accountsService->createForPlatform(
            (int) $data['network_id'],
            (int) $data['currency_id'],
            $this->requireCurrentUser()
        );

        return $this->successResponse('تم إنشاء أو استرجاع حساب الفوترة للشبكة.', new BillingAccountResource($account), 201);
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/subscriptions',
        summary: 'Create Subscription',
        tags: ['Billing'],
        operationId: 'billingfinance_createSubscription',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['billing_account_id', 'price_id'], properties: [new OA\Property(property: 'billing_account_id', type: 'integer'), new OA\Property(property: 'price_id', type: 'integer')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function createSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'billing_account_id' => ['required', 'integer', 'exists:billing_accounts,billing_account_id'],
            'price_id' => ['required', 'integer', 'exists:billing_prices,price_id'],
        ]);

        $result = $this->subscriptionsService->purchase(
            (int) $data['billing_account_id'],
            (int) $data['price_id'],
            $this->requireCurrentUser(),
            true
        );

        return $this->successResponse('تم إنشاء الاشتراك وإصدار الفاتورة وفق محرك الفوترة.', [
            'subscription' => new BillingSubscriptionResource($result['subscription']->load(['billingAccount.network', 'currency', 'plan', 'planVersion', 'price'])),
            'invoice' => $result['invoice'] ? new BillingInvoiceResource($result['invoice']) : null,
        ], 201);
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/subscriptions/{subscriptionId}',
        summary: 'Subscription Details',
        tags: ['Billing'],
        operationId: 'billingfinance_subscriptionDetails',
        parameters: [new OA\Parameter(name: 'subscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function subscriptionDetails(int $subscriptionId): JsonResponse
    {
        $subscription = BillingSubscription::query()
            ->with(['billingAccount.network', 'currency', 'plan', 'planVersion', 'price', 'trialRedemption', 'events'])
            ->findOrFail($subscriptionId);

        return $this->successResponse('تم استرجاع تفاصيل الاشتراك وسجل أحداثه.', [
            'subscription' => new BillingSubscriptionResource($subscription),
            'events' => $subscription->events->map(static fn ($event): array => [
                'event_id' => $event->event_id,
                'event_type' => $event->event_type,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'payload' => $event->payload,
                'actor_id' => $event->actor_id,
                'occurred_at' => $event->occurred_at->toDateTimeString(),
            ]),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/subscriptions/{subscriptionId}/cancel',
        summary: 'Cancel Subscription',
        tags: ['Billing'],
        operationId: 'billingfinance_cancelSubscription',
        parameters: [new OA\Parameter(name: 'subscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['cancel_at_period_end', 'reason'], properties: [new OA\Property(property: 'cancel_at_period_end', type: 'boolean'), new OA\Property(property: 'reason', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function cancelSubscription(Request $request, int $subscriptionId): JsonResponse
    {
        $data = $request->validate([
            'cancel_at_period_end' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $subscription = $this->subscriptionsService->cancel(
            $subscriptionId,
            (bool) $data['cancel_at_period_end'],
            $this->requireCurrentUser(),
            (string) $data['reason'],
            true
        );

        return $this->successResponse('تم تنفيذ إجراء إلغاء الاشتراك وتسجيل سببه في سجل التدقيق.', new BillingSubscriptionResource($subscription));
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/invoices',
        summary: 'Invoices',
        tags: ['Billing'],
        operationId: 'billingfinance_invoices',
        parameters: [new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'billing_account_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function invoices(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع فواتير الاشتراكات.',
            BillingInvoiceResource::collection($this->read->invoices($request->only([
                'network_id', 'billing_account_id', 'currency_id', 'status', 'search', 'per_page', 'all',
            ])))
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/payments',
        summary: 'Payments',
        tags: ['Billing'],
        operationId: 'billingfinance_payments',
        parameters: [new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'billing_account_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'invoice_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function payments(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع عمليات التحصيل.',
            BillingPaymentResource::collection($this->read->payments($request->only([
                'network_id', 'billing_account_id', 'invoice_id', 'currency_id', 'status', 'search', 'per_page', 'all',
            ])))
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/journal-entries',
        summary: 'Journal Entries',
        tags: ['Billing'],
        operationId: 'billingfinance_journalEntries',
        parameters: [new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'source_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function journalEntries(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع قيود دفتر الأستاذ.',
            LedgerJournalEntryResource::collection($this->read->journalEntries($request->only([
                'currency_id', 'source_type', 'from_date', 'to_date', 'per_page', 'all',
            ])))
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/payment-methods',
        summary: 'Payment Methods',
        tags: ['Billing'],
        operationId: 'billingfinance_paymentMethods',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function paymentMethods(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع وسائل الدفع النشطة.',
            PaymentMethodResource::collection($this->read->paymentMethods($request->integer('currency_id') ?: null))
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/treasury-accounts',
        summary: 'Treasury Accounts',
        tags: ['Billing'],
        operationId: 'billingfinance_treasuryAccounts',
        parameters: [new OA\Parameter(name: 'currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function treasuryAccounts(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع حسابات الخزينة.',
            TreasuryAccountResource::collection($this->treasury->list($request->only(['currency_id', 'status', 'search', 'per_page', 'all'])))
        );
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/treasury-accounts',
        summary: 'Store Treasury Account',
        tags: ['Billing'],
        operationId: 'billingfinance_storeTreasuryAccount',
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'account_type', type: 'string'), new OA\Property(property: 'currency_id', type: 'integer'), new OA\Property(property: 'institution_name', type: 'string'), new OA\Property(property: 'account_reference', type: 'string'), new OA\Property(property: 'status', type: 'string'), new OA\Property(property: 'metadata', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storeTreasuryAccount(StoreTreasuryAccountRequest $request): JsonResponse
    {
        try {
            $account = $this->treasury->create(
                $request->only(['name', 'account_type', 'currency_id', 'institution_name', 'account_reference', 'status', 'metadata']),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم إنشاء حساب الخزينة وربطه بحساب دفتر الأستاذ.', new TreasuryAccountResource($account), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Patch(
        path: '/api/v1/platform/billing/treasury-accounts/{treasuryAccountId}/status',
        summary: 'Set Treasury Status',
        tags: ['Billing'],
        operationId: 'billingfinance_setTreasuryStatus',
        parameters: [new OA\Parameter(name: 'treasuryAccountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['status'], properties: [new OA\Property(property: 'status', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function setTreasuryStatus(Request $request, int $treasuryAccountId): JsonResponse
    {
        try {
            $request->validate(['status' => ['required', 'in:ACTIVE,INACTIVE']]);
            $account = $this->treasury->setStatus(
                $treasuryAccountId,
                (string) $request->input('status'),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم تحديث حالة حساب الخزينة.', new TreasuryAccountResource($account));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/payments/{paymentId}/verify',
        summary: 'Verify Payment',
        tags: ['Billing'],
        operationId: 'billingfinance_verifyPayment',
        parameters: [new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function verifyPayment(VerifyPaymentRequest $request, int $paymentId): JsonResponse
    {
        try {
            $payment = $this->payments->verify(
                $paymentId,
                (int) $request->input('treasury_account_id'),
                $this->requireCurrentUser(),
                $request->input('note')
            );

            return $this->successResponse('تم اعتماد التحصيل وتحديث الفاتورة والاشتراك وفق المبلغ المسدد.', new BillingPaymentResource($payment));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/payments/{paymentId}/reject',
        summary: 'Reject Payment',
        tags: ['Billing'],
        operationId: 'billingfinance_rejectPayment',
        parameters: [new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function rejectPayment(RejectPaymentRequest $request, int $paymentId): JsonResponse
    {
        try {
            $payment = $this->payments->reject(
                $paymentId,
                $this->requireCurrentUser(),
                (string) $request->input('reason')
            );

            return $this->successResponse('تم رفض إثبات الدفع.', new BillingPaymentResource($payment));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/invoices/{invoiceId}/credit-notes',
        summary: 'Issue Credit Note',
        tags: ['Billing'],
        operationId: 'billingfinance_issueCreditNote',
        parameters: [new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function issueCreditNote(StoreCreditNoteRequest $request, int $invoiceId): JsonResponse
    {
        try {
            $note = $this->creditNotes->issue(
                $invoiceId,
                $request->input('lines'),
                (string) $request->input('reason'),
                (string) $request->input('idempotency_key'),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم إصدار الإشعار الدائن وتحديث الذمم ورصيد العميل.', [
                'credit_note_id' => $note->credit_note_id,
                'credit_note_number' => $note->credit_note_number,
                'invoice_id' => $note->invoice_id,
                'currency' => new CurrencyResource($note->currency),
                'accounting_treatment' => $note->accounting_treatment,
                'subtotal_minor' => $note->subtotal_minor,
                'tax_minor' => $note->tax_minor,
                'total_minor' => $note->total_minor,
                'applied_to_receivable_minor' => $note->applied_to_receivable_minor,
                'refundable_minor' => $note->refundable_minor,
                'refunded_minor' => $note->refunded_minor,
                'status' => $note->status,
                'reason' => $note->reason,
                'issued_at' => $note->issued_at,
                'lines' => $note->lines->map(fn ($line) => [
                    'invoice_line_id' => $line->invoice_line_id,
                    'description' => $line->description,
                    'amount_minor' => $line->amount_minor,
                ]),
            ], 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/payments/{paymentId}/refunds',
        summary: 'Refund Payment',
        tags: ['Billing'],
        operationId: 'billingfinance_refundPayment',
        parameters: [new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function refundPayment(StoreRefundRequest $request, int $paymentId): JsonResponse
    {
        try {
            $refund = $this->refunds->refund(
                $paymentId,
                (int) $request->input('credit_note_id'),
                (int) $request->input('treasury_account_id'),
                (int) $request->input('amount_minor'),
                (string) $request->input('reason'),
                (string) $request->input('idempotency_key'),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم تسوية الاسترداد من رصيد الإشعار الدائن وترحيل القيد المالي.', [
                'refund_id' => $refund->refund_id,
                'refund_reference' => $refund->refund_reference,
                'payment_id' => $refund->payment_id,
                'credit_note_id' => $refund->credit_note_id,
                'treasury_account_id' => $refund->treasury_account_id,
                'currency' => new CurrencyResource($refund->currency),
                'amount_minor' => $refund->amount_minor,
                'status' => $refund->status,
                'reason' => $refund->reason,
                'processed_at' => $refund->processed_at,
            ], 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/invoices/{invoiceId}/void',
        summary: 'Void Invoice',
        tags: ['Billing'],
        operationId: 'billingfinance_voidInvoice',
        parameters: [new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function voidInvoice(Request $request, int $invoiceId): JsonResponse
    {
        try {
            $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
            $invoice = $this->invoices->void(
                $invoiceId,
                (string) $request->input('reason'),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم إلغاء الفاتورة وعكس قيدها المالي.', new BillingInvoiceResource($invoice));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/exchange-rates',
        summary: 'Exchange Rates',
        tags: ['Billing'],
        operationId: 'billingfinance_exchangeRates',
        parameters: [new OA\Parameter(name: 'base_currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'quote_currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function exchangeRates(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع أسعار الصرف المسجلة.',
            CurrencyExchangeRateResource::collection($this->read->exchangeRates($request->only([
                'base_currency_id', 'quote_currency_id', 'per_page', 'all',
            ])))
        );
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/exchange-rates',
        summary: 'Store Exchange Rate',
        tags: ['Billing'],
        operationId: 'billingfinance_storeExchangeRate',
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storeExchangeRate(StoreExchangeRateRequest $request): JsonResponse
    {
        try {
            $rate = $this->exchange->recordRate(
                (int) $request->input('base_currency_id'),
                (int) $request->input('quote_currency_id'),
                (int) $request->input('rate_numerator'),
                (int) $request->input('rate_denominator'),
                (string) $request->input('source'),
                $this->requireCurrentUser(),
                $request->date('expires_at')
            );

            return $this->successResponse('تم تسجيل سعر الصرف ككسر دقيق مع مصدره وتوقيته.', new CurrencyExchangeRateResource($rate->load(['baseCurrency', 'quoteCurrency'])), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/conversions',
        summary: 'Convert Currency',
        tags: ['Billing'],
        operationId: 'billingfinance_convertCurrency',
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function convertCurrency(StoreCurrencyConversionRequest $request): JsonResponse
    {
        try {
            $conversion = $this->exchange->convert(
                (int) $request->input('exchange_rate_id'),
                (int) $request->input('source_amount_minor'),
                (int) $request->input('fee_amount_minor', 0),
                (string) $request->input('source_type'),
                (int) $request->input('source_id'),
                $this->requireCurrentUser()
            );

            return $this->successResponse(
                'تم تسجيل تحويل العملة مع لقطة سعر الصرف والرسوم والتقريب.',
                new CurrencyConversionResource($conversion->load(['fromCurrency', 'toCurrency', 'exchangeRate'])),
                201
            );
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/conversions',
        summary: 'Conversions',
        tags: ['Billing'],
        operationId: 'billingfinance_conversions',
        parameters: [new OA\Parameter(name: 'from_currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'to_currency_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function conversions(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع سجلات تحويل العملات.',
            CurrencyConversionResource::collection($this->read->conversions($request->only([
                'from_currency_id', 'to_currency_id', 'per_page', 'all',
            ])))
        );
    }
}
