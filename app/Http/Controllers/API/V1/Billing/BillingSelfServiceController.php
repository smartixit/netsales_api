<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Billing\{CreateBillingAccountRequest, PurchaseSubscriptionRequest, StorePaymentRequest};
use App\Http\Resources\API\V1\Billing\{BillingAccountResource, BillingCreditNoteResource, BillingInvoiceResource, BillingPaymentResource, BillingPlanResource, BillingRefundResource, BillingSubscriptionEventResource, BillingSubscriptionResource, PaymentMethodResource};
use App\Services\Billing\{BillingPaymentService, BillingPlatformReadService, BillingSelfService, BillingSubscriptionService, CurrencyService};
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

    public function plans(Request $request, int $billingAccountId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع الخطط والأسعار المتاحة بعملة حساب الفوترة.',
            BillingPlanResource::collection($this->self->plans(
                $billingAccountId,
                $request->only(['search', 'per_page', 'all']),
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
                $request->only(['status', 'per_page', 'all']),
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
                $request->only(['status', 'per_page', 'all']),
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
                $request->only(['status', 'per_page', 'all']),
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
                $request->only(['status', 'per_page', 'all']),
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
                $request->only(['status', 'per_page', 'all']),
                $this->requireCurrentUser()
            ))
        );
    }

    public function subscriptionEvents(int $subscriptionId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع سجل دورة الاشتراك.',
            BillingSubscriptionEventResource::collection(
                $this->self->subscriptionEvents($subscriptionId, $this->requireCurrentUser())
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
