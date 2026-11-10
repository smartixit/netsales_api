<?php

namespace App\Services\Billing;

use App\Enums\BillingPaymentStatus;
use App\Models\{BillingCreditNote, BillingInvoice, BillingPayment, BillingRefund, TreasuryAccount, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingRefundService
{
    public function __construct(
        private BillingLedgerService $ledger,
        private BillingLedgerChartService $chart,
        private BillingAuthorizationService $authorization
    ) {}

    public function refund(
        int $paymentId,
        int $creditNoteId,
        int $treasuryAccountId,
        int $amountMinor,
        string $reason,
        string $idempotencyKey,
        User $actor
    ): BillingRefund {
        $this->authorization->assertPlatform($actor);

        if ($amountMinor <= 0 || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['refund' => ['مبلغ الاسترداد ومفتاح منع التكرار غير صالحين.']]);
        }

        return DB::transaction(function () use (
            $paymentId, $creditNoteId, $treasuryAccountId, $amountMinor, $reason, $idempotencyKey, $actor
        ): BillingRefund {
            $existing = BillingRefund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->payment_id !== $paymentId
                    || (int) $existing->credit_note_id !== $creditNoteId
                    || (int) $existing->amount_minor !== $amountMinor
                    || $existing->reason !== $reason) {
                    throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مستخدم لعملية استرداد مختلفة.']]);
                }
                return $existing->load(['payment', 'currency', 'creditNote']);
            }

            $invoiceId = BillingCreditNote::query()->whereKey($creditNoteId)->value('invoice_id');
            if ($invoiceId === null) {
                throw ValidationException::withMessages(['credit_note_id' => ['الإشعار الدائن غير موجود.']]);
            }

            // Global lock order: Invoice -> Payment -> Credit Note -> Treasury.
            $invoice = BillingInvoice::query()->lockForUpdate()->findOrFail((int) $invoiceId);
            $payment = BillingPayment::query()->with('currency')->lockForUpdate()->findOrFail($paymentId);
            $creditNote = BillingCreditNote::query()->lockForUpdate()->findOrFail($creditNoteId);

            // Recheck the idempotency key after acquiring the invoice lock.
            // This closes the race where two concurrent requests both pass the initial lookup.
            $existing = BillingRefund::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if ((int) $existing->payment_id !== $paymentId
                    || (int) $existing->credit_note_id !== $creditNoteId
                    || (int) $existing->amount_minor !== $amountMinor
                    || $existing->reason !== $reason) {
                    throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مستخدم لعملية استرداد مختلفة.']]);
                }
                return $existing->load(['payment', 'currency', 'creditNote']);
            }

            if ((int) $payment->invoice_id !== (int) $invoice->invoice_id
                || (int) $creditNote->invoice_id !== (int) $invoice->invoice_id
                || (int) $payment->currency_id !== (int) $creditNote->currency_id
                || (int) $invoice->currency_id !== (int) $creditNote->currency_id) {
                throw ValidationException::withMessages(['credit_note_id' => ['يجب أن ترتبط الدفعة والإشعار الدائن والفاتورة بالحساب والعملة نفسيهما.']]);
            }

            if (! in_array($payment->status, [
                BillingPaymentStatus::VERIFIED,
                BillingPaymentStatus::PARTIALLY_REFUNDED,
            ], true)) {
                throw ValidationException::withMessages(['payment_id' => ['لا يمكن استرداد دفعة غير معتمدة أو مستردة بالكامل.']]);
            }

            if (! in_array($creditNote->status, ['ISSUED', 'PARTIALLY_REFUNDED'], true)) {
                throw ValidationException::withMessages(['credit_note_id' => ['الإشعار الدائن غير متاح للاسترداد.']]);
            }

            $alreadyRefundedFromPayment = (int) BillingRefund::query()
                ->where('payment_id', $payment->payment_id)
                ->where('status', 'COMPLETED')
                ->sum('amount_minor');
            $remainingOnPayment = (int) $payment->amount_minor - $alreadyRefundedFromPayment;

            $remainingOnCredit = (int) $creditNote->refundable_minor - (int) $creditNote->refunded_minor;

            if ($amountMinor > $remainingOnPayment || $amountMinor > $remainingOnCredit) {
                throw ValidationException::withMessages([
                    'amount_minor' => ['مبلغ الاسترداد يتجاوز الرصيد المتاح إما للدفعة أو للإشعار الدائن.'],
                ]);
            }

            $treasury = TreasuryAccount::query()
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->findOrFail($treasuryAccountId);

            if ((int) $treasury->currency_id !== (int) $payment->currency_id) {
                throw ValidationException::withMessages(['treasury_account_id' => ['عملة حساب الخزينة لا تطابق عملة الدفعة.']]);
            }

            $treasuryLedger = $this->chart->ensureTreasuryAccount($treasury);
            $accounts = $this->chart->ensureForCurrency($payment->currency);

            $refund = BillingRefund::query()->create([
                'refund_reference' => 'NS-REF-'.now('Asia/Aden')->format('Ymd').'-'.Str::upper(Str::random(8)),
                'idempotency_key' => $idempotencyKey,
                'payment_id' => $payment->payment_id,
                'credit_note_id' => $creditNote->credit_note_id,
                'treasury_account_id' => $treasury->treasury_account_id,
                'currency_id' => $payment->currency_id,
                'amount_minor' => $amountMinor,
                'status' => 'PENDING',
                'reason' => $reason,
                'processed_by' => $actor->user_id,
            ]);

            // The credit note already reduced A/R (and created a customer credit when overpaid).
            // Cash settlement consumes that customer credit: Dr A/R, Cr Treasury.
            $this->ledger->post(
                (int) $payment->currency_id,
                'تسوية استرداد '.$refund->refund_reference.' من الإشعار '.$creditNote->credit_note_number,
                'billing-refund-'.$refund->refund_id,
                [
                    ['ledger_account_id' => $accounts['2200']->ledger_account_id, 'debit_minor' => $amountMinor, 'credit_minor' => 0],
                    ['ledger_account_id' => $treasuryLedger->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $amountMinor],
                ],
                $actor,
                'BILLING_REFUND',
                (int) $refund->refund_id
            );

            $refund->status = 'COMPLETED';
            $refund->processed_at = now('Asia/Aden');
            $refund->save();

            $creditNote->refunded_minor += $amountMinor;
            if ($creditNote->refunded_minor === (int) $creditNote->refundable_minor) {
                $creditNote->status = 'REFUNDED';
            } else {
                $creditNote->status = 'PARTIALLY_REFUNDED';
            }
            $creditNote->save();

            $newPaymentRefunded = $alreadyRefundedFromPayment + $amountMinor;
            $payment->status = $newPaymentRefunded === (int) $payment->amount_minor
                ? BillingPaymentStatus::REFUNDED
                : BillingPaymentStatus::PARTIALLY_REFUNDED;
            $payment->save();

            return $refund->load(['payment', 'currency', 'creditNote', 'treasuryAccount']);
        }, 3);
    }
}
