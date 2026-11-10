<?php

namespace App\Services\Billing;

use App\Enums\BillingPaymentStatus;
use App\Models\{BillingPayment, BillingRefund, TreasuryAccount, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OverflowException;

class BillingRefundService
{
    public function __construct(
        private BillingLedgerService $ledger,
        private BillingLedgerChartService $chart,
        private BillingAuthorizationService $authorization
    ) {}

    public function refund(int $paymentId, int $treasuryAccountId, int $amountMinor, string $reason, string $idempotencyKey, User $actor): BillingRefund
    {
        $this->authorization->assertPlatform($actor);

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount_minor' => ['يجب أن يكون مبلغ الاسترداد أكبر من صفر.']]);
        }

        return DB::transaction(function () use ($paymentId, $treasuryAccountId, $amountMinor, $reason, $idempotencyKey, $actor): BillingRefund {
            $existing = BillingRefund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->payment_id !== $paymentId
                    || (int) $existing->amount_minor !== $amountMinor
                    || $existing->reason !== $reason) {
                    throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مستخدم لطلب استرداد مختلف.']]);
                }

                return $existing->load(['payment', 'currency']);
            }

            $payment = BillingPayment::query()->with('currency')->lockForUpdate()->findOrFail($paymentId);

            if (! in_array($payment->status, [BillingPaymentStatus::VERIFIED, BillingPaymentStatus::PARTIALLY_REFUNDED], true)) {
                throw ValidationException::withMessages(['payment_id' => ['لا يمكن استرداد دفعة غير معتمدة أو سبق استردادها بالكامل.']]);
            }

            $alreadyRefunded = (int) BillingRefund::query()
                ->where('payment_id', $payment->payment_id)
                ->where('status', 'COMPLETED')
                ->sum('amount_minor');

            if ($alreadyRefunded > (int) $payment->amount_minor
                || $amountMinor > (int) $payment->amount_minor - $alreadyRefunded) {
                throw ValidationException::withMessages(['amount_minor' => ['مبلغ الاسترداد يتجاوز الرصيد القابل للاسترداد من الدفعة.']]);
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
                'currency_id' => $payment->currency_id,
                'amount_minor' => $amountMinor,
                'status' => 'PENDING',
                'reason' => $reason,
                'processed_by' => $actor->user_id,
            ]);

            $this->ledger->post(
                (int) $payment->currency_id,
                'استرداد دفعة '.$payment->payment_reference.' - '.$refund->refund_reference,
                'billing-refund-'.$refund->refund_id,
                [
                    ['ledger_account_id' => $accounts['4190']->ledger_account_id, 'debit_minor' => $amountMinor, 'credit_minor' => 0],
                    ['ledger_account_id' => $treasuryLedger->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $amountMinor],
                ],
                $actor,
                'BILLING_REFUND',
                (int) $refund->refund_id
            );

            $refund->status = 'COMPLETED';
            $refund->processed_at = now('Asia/Aden');
            $refund->save();

            $newRefundedTotal = $alreadyRefunded + $amountMinor;
            $payment->status = $newRefundedTotal === (int) $payment->amount_minor
                ? BillingPaymentStatus::REFUNDED
                : BillingPaymentStatus::PARTIALLY_REFUNDED;
            $payment->save();

            return $refund->load(['payment', 'currency']);
        }, 3);
    }
}
