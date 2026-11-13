<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Billing\{CreateBillingAccountRequest, PurchaseSubscriptionRequest, StorePaymentRequest, StoreBillingAccountContactRequest, UpdateBillingAccountContactRequest, UpdateBillingAccountRequest};
use App\Http\Resources\API\V1\Billing\{BillingAccountContactResource, BillingAccountResource, BillingCreditNoteResource, BillingInvoiceResource, BillingPaymentResource, BillingPlanResource, BillingRefundResource, BillingSubscriptionEventResource, BillingSubscriptionResource, BillingUsageAggregateResource, PaymentMethodResource};
use App\Services\Billing\{BillingEntitlementService, BillingPaymentService, BillingPlatformReadService, BillingSelfService, BillingSubscriptionService, CurrencyService};
use App\Services\Support\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    public function currencies(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع العملات المتاحة لفتح حساب فوترة.',
            CurrencyResource::collection($this->currencies->listActive())
        );
    }

    public function accounts(int $networkId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع حسابات الفوترة الخاصة بالشبكة وعملاتها.',
            BillingAccountResource::collection($this->self->accounts($networkId, $this->requireCurrentUser()))
        );
    }

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

    public function account(int $billingAccountId): JsonResponse
    {
        return $this->successResponse('تم استرجاع ملف حساب الفوترة وجهات الاتصال.', new BillingAccountResource($this->self->account($billingAccountId, $this->requireCurrentUser())));
    }

    public function updateAccount(UpdateBillingAccountRequest $request, int $billingAccountId): JsonResponse
    {
        try {
            $account = $this->self->updateAccount($billingAccountId, $request->validated(), $this->requireCurrentUser());
            return $this->successResponse('تم تحديث بيانات حساب الفوترة.', new BillingAccountResource($account));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    public function storeContact(StoreBillingAccountContactRequest $request, int $billingAccountId): JsonResponse
    {
        try {
            $contact = $this->self->createContact($billingAccountId, $request->validated(), $this->requireCurrentUser());
            return $this->successResponse('تمت إضافة جهة اتصال لحساب الفوترة.', new BillingAccountContactResource($contact), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    public function updateContact(UpdateBillingAccountContactRequest $request, int $billingAccountId, int $contactId): JsonResponse
    {
        try {
            $contact = $this->self->updateContact($billingAccountId, $contactId, $request->validated(), $this->requireCurrentUser());
            return $this->successResponse('تم تحديث جهة اتصال حساب الفوترة.', new BillingAccountContactResource($contact));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    public function destroyContact(int $billingAccountId, int $contactId): JsonResponse
    {
        try {
            $this->self->deleteContact($billingAccountId, $contactId, $this->requireCurrentUser());
            return $this->successResponse('تم حذف جهة اتصال حساب الفوترة.', null);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

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

        $subscription->loadMissing(['planVersion.features', 'currency', 'trialRedemption.policyVersion']);
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
                'start' => $subscription->trial_start?->toDateTimeString(),
                'end' => $subscription->trial_end?->toDateTimeString(),
                'entitlement_mode' => $trial['entitlement_mode'] ?? 'FULL_PLAN',
                'allowed_features' => $trial['entitlement_config']['allowed_features'] ?? [],
                'awaiting_first_activation' => $status === 'INCOMPLETE'
                    && ($trial['start_trigger'] ?? null) === 'FIRST_ACTIVATION',
            ] : null,
            'features' => $features,
        ]);
    }

    public function plans(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع الخطط والأسعار المتاحة بعملة حساب الفوترة.',
            BillingPlanResource::collection($this->self->plans(
                $billingAccountId,
                $request->only(['search', 'per_page', 'cursor', 'all']),
                $this->requireCurrentUser()
            ))
        );
    }

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

    public function subscriptions(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع اشتراكات حساب الفوترة.',
            BillingSubscriptionResource::collection($this->self->subscriptions(
                $billingAccountId,
                $request->only(['status', 'per_page', 'cursor', 'all']),
                $this->requireCurrentUser()
            ))
        );
    }

    public function invoices(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع فواتير حساب الفوترة.',
            BillingInvoiceResource::collection($this->self->invoices(
                $billingAccountId,
                $request->only(['status', 'per_page', 'cursor', 'all']),
                $this->requireCurrentUser()
            ))
        );
    }

    public function invoice(int $invoiceId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع تفاصيل الفاتورة.',
            new BillingInvoiceResource($this->self->invoice($invoiceId, $this->requireCurrentUser()))
        );
    }

    public function payments(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع مدفوعات حساب الفوترة.',
            BillingPaymentResource::collection($this->self->payments(
                $billingAccountId,
                $request->only(['status', 'per_page', 'cursor', 'all']),
                $this->requireCurrentUser()
            ))
        );
    }

    public function creditNotes(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع الإشعارات الدائنة لحساب الفوترة.',
            BillingCreditNoteResource::collection($this->self->creditNotes(
                $billingAccountId,
                $request->only(['status', 'per_page', 'cursor', 'all']),
                $this->requireCurrentUser()
            ))
        );
    }

    public function refunds(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع عمليات الاسترداد لحساب الفوترة.',
            BillingRefundResource::collection($this->self->refunds(
                $billingAccountId,
                $request->only(['status', 'per_page', 'cursor', 'all']),
                $this->requireCurrentUser()
            ))
        );
    }

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

    public function paymentMethods(int $billingAccountId): JsonResponse
    {
        $account = $this->self->account($billingAccountId, $this->requireCurrentUser());

        return $this->successResponse(
            'تم استرجاع وسائل الدفع المتاحة بعملة حساب الفوترة.',
            PaymentMethodResource::collection($this->read->paymentMethods((int) $account->default_currency_id))
        );
    }

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
