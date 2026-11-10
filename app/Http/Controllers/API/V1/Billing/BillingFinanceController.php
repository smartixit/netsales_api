<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Billing\{RejectPaymentRequest, StoreCurrencyConversionRequest, StoreExchangeRateRequest, StoreRefundRequest, StoreCreditNoteRequest, StoreTreasuryAccountRequest, VerifyPaymentRequest};
use App\Http\Resources\API\V1\Billing\{BillingAccountResource, BillingInvoiceResource, BillingPaymentResource, BillingSubscriptionResource, CurrencyExchangeRateResource, CurrencyConversionResource, CurrencyResource, LedgerJournalEntryResource, PaymentMethodResource, TreasuryAccountResource};
use App\Services\Billing\{BillingDashboardService, BillingCreditNoteService, BillingInvoiceService, BillingPaymentService, BillingPlatformReadService, BillingRefundService, BillingTreasuryService, CurrencyExchangeService};
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
        private CurrencyExchangeService $exchange,
        private BillingDashboardService $dashboard,
        private BillingRefundService $refunds,
        private BillingCreditNoteService $creditNotes
    ) {}

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
