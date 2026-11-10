<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Billing\{RejectPaymentRequest, StoreExchangeRateRequest, StoreTreasuryAccountRequest, VerifyPaymentRequest};
use App\Http\Resources\API\V1\Billing\{BillingAccountResource, BillingInvoiceResource, BillingPaymentResource, BillingSubscriptionResource, CurrencyExchangeRateResource, CurrencyConversionResource, LedgerJournalEntryResource, PaymentMethodResource, TreasuryAccountResource};
use App\Services\Billing\{BillingInvoiceService, BillingPaymentService, BillingPlatformReadService, BillingTreasuryService, CurrencyExchangeService};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillingFinanceController extends BaseController
{
    public function __construct(
        private BillingPlatformReadService $read,
        private BillingTreasuryService $treasury,
        private BillingPaymentService $payments,
        private BillingInvoiceService $invoices,
        private CurrencyExchangeService $exchange
    ) {}

    public function accounts(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع حسابات الفوترة.',
            BillingAccountResource::collection($this->read->accounts($request->only(['network_id', 'currency_id', 'status', 'per_page', 'all'])))
        );
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع اشتراكات المنصة.',
            BillingSubscriptionResource::collection($this->read->subscriptions($request->only([
                'network_id', 'billing_account_id', 'currency_id', 'status', 'per_page', 'all',
            ])))
        );
    }

    public function invoices(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع فواتير الاشتراكات.',
            BillingInvoiceResource::collection($this->read->invoices($request->only([
                'network_id', 'billing_account_id', 'currency_id', 'status', 'search', 'per_page', 'all',
            ])))
        );
    }

    public function payments(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع عمليات التحصيل.',
            BillingPaymentResource::collection($this->read->payments($request->only([
                'network_id', 'billing_account_id', 'invoice_id', 'currency_id', 'status', 'search', 'per_page', 'all',
            ])))
        );
    }

    public function journalEntries(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع قيود دفتر الأستاذ.',
            LedgerJournalEntryResource::collection($this->read->journalEntries($request->only([
                'currency_id', 'source_type', 'from_date', 'to_date', 'per_page', 'all',
            ])))
        );
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع وسائل الدفع النشطة.',
            PaymentMethodResource::collection($this->read->paymentMethods($request->integer('currency_id') ?: null))
        );
    }

    public function treasuryAccounts(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع حسابات الخزينة.',
            TreasuryAccountResource::collection($this->treasury->list($request->only(['currency_id', 'status', 'search', 'per_page', 'all'])))
        );
    }

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

    public function exchangeRates(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع أسعار الصرف المسجلة.',
            CurrencyExchangeRateResource::collection($this->read->exchangeRates($request->only([
                'base_currency_id', 'quote_currency_id', 'per_page', 'all',
            ])))
        );
    }

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
