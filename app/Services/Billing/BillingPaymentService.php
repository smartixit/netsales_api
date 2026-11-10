<?php

namespace App\Services\Billing;

use App\Enums\{BillingInvoiceStatus, BillingPaymentStatus, BillingSubscriptionStatus};
use App\Models\{BillingInvoice, BillingPayment, BillingPaymentProof, BillingPaymentIntent, BillingPaymentMethod, BillingSubscription, BillingSubscriptionEvent, Currency, TreasuryAccount, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OverflowException;

class BillingPaymentService
{
    public function __construct(
        private BillingLedgerService $ledger,
        private BillingLedgerChartService $chart,
        private BillingAuthorizationService $authorization
    ) {}

    public function recordManualPayment(
        int $invoiceId,
        int $paymentMethodId,
        int $amountMinor,
        string $idempotencyKey,
        User $actor,
        ?string $externalReference = null,
        ?array $proof = null
    ): BillingPayment {
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مطلوب ولا يتجاوز 128 حرفاً.']]);
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount_minor' => ['يجب أن تكون قيمة الدفعة أكبر من صفر.']]);
        }

        return DB::transaction(function () use ($invoiceId, $paymentMethodId, $amountMinor, $idempotencyKey, $actor, $externalReference, $proof): BillingPayment {
            $invoice = BillingInvoice::query()
                ->with('billingAccount')
                ->lockForUpdate()
                ->findOrFail($invoiceId);
            $this->authorization->assertBillingAccountAccess($actor, $invoice->billingAccount);

            $existing = BillingPayment::query()
                ->with(['currency', 'proofs'])
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if ((int) $existing->invoice_id !== $invoiceId
                    || (int) $existing->payment_method_id !== $paymentMethodId
                    || (int) $existing->amount_minor !== $amountMinor) {
                    throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مستخدم لدفعة ببيانات مختلفة.']]);
                }

                return $existing;
            }

            if (! in_array($invoice->status, [BillingInvoiceStatus::OPEN, BillingInvoiceStatus::PARTIALLY_PAID, BillingInvoiceStatus::PARTIALLY_CREDITED], true)
                || (int) $invoice->remaining_minor <= 0) {
                throw ValidationException::withMessages(['invoice_id' => ['الفاتورة غير مستحقة للدفع حالياً.']]);
            }

            $pendingMinor = (int) BillingPayment::query()
                ->where('invoice_id', $invoice->invoice_id)
                ->where('status', BillingPaymentStatus::PENDING_REVIEW)
                ->sum('amount_minor');
            $availableMinor = (int) $invoice->remaining_minor - $pendingMinor;

            if ($amountMinor > $availableMinor) {
                throw ValidationException::withMessages([
                    'amount_minor' => ['قيمة الدفعة تتجاوز الرصيد المتاح بعد احتساب طلبات الدفع المعلقة قيد المراجعة.'],
                ]);
            }

            $method = BillingPaymentMethod::query()
                ->where('is_active', true)
                ->whereKey($paymentMethodId)
                ->whereHas('currencies', fn ($query) => $query
                    ->where('currencies.currency_id', $invoice->currency_id)
                    ->where('billing_payment_method_currencies.is_active', true))
                ->first();

            if (! $method) {
                throw ValidationException::withMessages(['payment_method_id' => ['وسيلة الدفع غير متاحة بعملة الفاتورة.']]);
            }

            if ($method->requires_proof && empty($proof['file_path'])) {
                throw ValidationException::withMessages(['proof' => ['إرفاق إثبات الدفع مطلوب لهذه الوسيلة.']]);
            }

            $payment = BillingPayment::query()->create([
                'payment_reference' => 'NS-PAY-'.now('Asia/Aden')->format('Ymd').'-'.Str::upper(Str::random(8)),
                'idempotency_key' => $idempotencyKey,
                'invoice_id' => $invoice->invoice_id,
                'billing_account_id' => $invoice->billing_account_id,
                'currency_id' => $invoice->currency_id,
                'payment_method_id' => $method->payment_method_id,
                'amount_minor' => $amountMinor,
                'status' => BillingPaymentStatus::PENDING_REVIEW,
                'external_reference' => $externalReference,
                'received_at' => now('Asia/Aden'),
                'recorded_by' => $actor->user_id,
            ]);

            if ($proof !== null && ! empty($proof['file_path'])) {
                $payment->proofs()->create([
                    'file_path' => $proof['file_path'],
                    'original_name' => $proof['original_name'] ?? null,
                    'mime_type' => $proof['mime_type'] ?? null,
                    'file_size' => $proof['file_size'] ?? null,
                    'sha256' => $proof['sha256'] ?? null,
                    'uploaded_by' => $actor->user_id,
                    'uploaded_at' => now('Asia/Aden'),
                ]);
            }

            return $payment->load(['invoice', 'currency', 'paymentMethod', 'proofs']);
        }, 3);
    }

    public function verify(int $paymentId, int $treasuryAccountId, User $reviewer, ?string $note = null): BillingPayment
    {
        $this->authorization->assertPlatform($reviewer);

        return DB::transaction(function () use ($paymentId, $treasuryAccountId, $reviewer, $note): BillingPayment {
            // Keep the global billing lock order consistent: Invoice -> Payment -> Treasury.
            // The payment's invoice_id is immutable after creation, so the initial read only locates its parent.
            $paymentInvoiceId = BillingPayment::query()
                ->whereKey($paymentId)
                ->value('invoice_id');

            if ($paymentInvoiceId === null) {
                throw ValidationException::withMessages(['payment_id' => ['سجل الدفعة غير موجود.']]);
            }

            $invoice = BillingInvoice::query()->lockForUpdate()->findOrFail((int) $paymentInvoiceId);
            $payment = BillingPayment::query()
                ->with(['currency', 'paymentMethod'])
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ((int) $payment->invoice_id !== (int) $invoice->invoice_id) {
                throw ValidationException::withMessages(['invoice_id' => ['ارتباط الدفعة بالفاتورة غير متسق.']]);
            }

            if ($payment->status === BillingPaymentStatus::VERIFIED) {
                return $payment->load(['invoice', 'treasuryAccount', 'proofs']);
            }

            if ($payment->status !== BillingPaymentStatus::PENDING_REVIEW) {
                throw ValidationException::withMessages(['payment_id' => ['لا يمكن اعتماد دفعة ليست قيد المراجعة.']]);
            }

            if (! in_array($invoice->status, [BillingInvoiceStatus::OPEN, BillingInvoiceStatus::PARTIALLY_PAID], true)
                || (int) $invoice->remaining_minor < (int) $payment->amount_minor) {
                throw ValidationException::withMessages(['invoice_id' => ['لم تعد الفاتورة تسمح بهذه الدفعة أو تغير المبلغ المتبقي.']]);
            }

            if ((int) $payment->billing_account_id !== (int) $invoice->billing_account_id) {
                throw ValidationException::withMessages(['billing_account_id' => ['حساب الدفعة لا يطابق حساب الفاتورة.']]);
            }

            if ((int) $payment->currency_id !== (int) $invoice->currency_id) {
                throw ValidationException::withMessages(['currency_id' => ['عملة الدفعة لا تطابق عملة الفاتورة.']]);
            }

            $treasury = TreasuryAccount::query()
                ->with('currency')
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->findOrFail($treasuryAccountId);

            if ((int) $treasury->currency_id !== (int) $payment->currency_id) {
                throw ValidationException::withMessages(['treasury_account_id' => ['عملة حساب الخزينة لا تطابق عملة الدفعة.']]);
            }

            $accountReceivable = $this->chart->ensureForCurrency($payment->currency)['1100'];
            $treasuryLedger = $this->chart->ensureTreasuryAccount($treasury);

            $this->ledger->post(
                (int) $payment->currency_id,
                'تحصيل دفعة '.$payment->payment_reference,
                'billing-payment-verified-'.$payment->payment_id,
                [
                    ['ledger_account_id' => $treasuryLedger->ledger_account_id, 'debit_minor' => (int) $payment->amount_minor, 'credit_minor' => 0],
                    ['ledger_account_id' => $accountReceivable->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => (int) $payment->amount_minor],
                ],
                $reviewer,
                'BILLING_PAYMENT',
                (int) $payment->payment_id
            );

            $payment->status = BillingPaymentStatus::VERIFIED;
            $payment->treasury_account_id = $treasury->treasury_account_id;
            $payment->verified_at = now('Asia/Aden');
            $payment->verified_by = $reviewer->user_id;
            $payment->review_note = $note;
            $payment->save();

            $invoice->paid_minor = $this->safeAdd((int) $invoice->paid_minor, (int) $payment->amount_minor);
            $invoice->remaining_minor = max(0, (int) $invoice->total_minor - (int) $invoice->paid_minor - (int) $invoice->credited_minor);
            $invoice->status = $invoice->remaining_minor === 0
                ? (((int) $invoice->credited_minor >= (int) $invoice->total_minor) ? BillingInvoiceStatus::CREDITED : BillingInvoiceStatus::PAID)
                : ((int) $invoice->credited_minor > 0 ? BillingInvoiceStatus::PARTIALLY_CREDITED : BillingInvoiceStatus::PARTIALLY_PAID);
            if ($invoice->remaining_minor === 0) {
                $invoice->paid_at = now('Asia/Aden');
            }
            $invoice->save();

            if ($invoice->remaining_minor === 0 && $invoice->billing_subscription_id !== null) {
                $subscription = BillingSubscription::query()
                    ->lockForUpdate()
                    ->find($invoice->billing_subscription_id);

                $expiredInitialSubscription = $subscription
                    && $subscription->status === BillingSubscriptionStatus::EXPIRED
                    && $subscription->events()
                        ->where('event_type', 'INITIAL_SUBSCRIPTION_EXPIRED_FOR_NON_PAYMENT')
                        ->exists();

                if ($subscription && (
                    in_array($subscription->status, [
                        BillingSubscriptionStatus::INCOMPLETE,
                        BillingSubscriptionStatus::PAST_DUE,
                        BillingSubscriptionStatus::SUSPENDED,
                    ], true) || $expiredInitialSubscription
                )) {
                    $oldStatus = $subscription->status->value;
                    $periodRestarted = false;

                    if ($invoice->period_start !== null && $invoice->period_end !== null) {
                        if ($invoice->period_end->endOfDay()->lte(now('Asia/Aden'))) {
                            // Do not grant elapsed historical time after a late settlement.
                            $this->startPaidPeriod($subscription);
                            $periodRestarted = true;
                        } else {
                            $subscription->current_period_start = $invoice->period_start->startOfDay();
                            $subscription->current_period_end = $invoice->period_end->endOfDay();
                        }
                    } else {
                        $this->startPaidPeriod($subscription);
                    }

                    $subscription->status = BillingSubscriptionStatus::ACTIVE;
                    $subscription->grace_period_ends_at = null;
                    $subscription->paused_at = null;
                    $subscription->ended_at = null;
                    $subscription->save();

                    $recognitionSchedules = DB::table('billing_revenue_recognition_schedules')
                        ->where('invoice_id', $invoice->invoice_id)
                        ->where('status', 'HOLD');

                    $recognitionSchedules->where('revenue_account_code', '4120')
                        ->update([
                            'status' => 'PENDING',
                            'service_start_on' => now('Asia/Aden')->toDateString(),
                            'service_end_on' => now('Asia/Aden')->toDateString(),
                            'updated_at' => now('Asia/Aden'),
                        ]);

                    $subscriptionSchedules = DB::table('billing_revenue_recognition_schedules')
                        ->where('invoice_id', $invoice->invoice_id)
                        ->where('status', 'HOLD')
                        ->where('revenue_account_code', '4100');

                    if ($periodRestarted) {
                        $subscriptionSchedules->update([
                            'status' => 'PENDING',
                            'service_start_on' => $subscription->current_period_start?->toDateString(),
                            'service_end_on' => $subscription->current_period_end?->toDateString(),
                            'updated_at' => now('Asia/Aden'),
                        ]);
                    } else {
                        $subscriptionSchedules->update([
                            'status' => 'PENDING',
                            'updated_at' => now('Asia/Aden'),
                        ]);
                    }

                    $this->dunning->scheduleSubscriptionAlerts($subscription);

                    BillingSubscriptionEvent::query()->create([
                        'billing_subscription_id' => $subscription->billing_subscription_id,
                        'event_type' => in_array($oldStatus, [
                            BillingSubscriptionStatus::SUSPENDED->value,
                            BillingSubscriptionStatus::EXPIRED->value,
                        ], true) ? 'PAYMENT_RECOVERED' : 'PAYMENT_SUCCEEDED',
                        'from_status' => $oldStatus,
                        'to_status' => BillingSubscriptionStatus::ACTIVE->value,
                        'payload' => [
                            'invoice_id' => $invoice->invoice_id,
                            'payment_id' => $payment->payment_id,
                            'paid_minor' => $invoice->paid_minor,
                            'currency_id' => $invoice->currency_id,
                            'period_restarted_after_lapse' => $periodRestarted,
                        ],
                        'actor_id' => $reviewer->user_id,
                        'occurred_at' => now('Asia/Aden'),
                    ]);
                }
            }

            if ($payment->payment_intent_id) {
                BillingPaymentIntent::query()
                    ->whereKey($payment->payment_intent_id)
                    ->where('status', '!=', 'SUCCEEDED')
                    ->update(['status' => 'SUCCEEDED', 'succeeded_at' => now('Asia/Aden')]);
            }

            return $payment->refresh()->load(['invoice', 'currency', 'paymentMethod', 'treasuryAccount', 'proofs']);
        }, 3);
    }

    public function reject(int $paymentId, User $reviewer, string $reason): BillingPayment
    {
        $this->authorization->assertPlatform($reviewer);

        return DB::transaction(function () use ($paymentId, $reviewer, $reason): BillingPayment {
            $payment = BillingPayment::query()->lockForUpdate()->findOrFail($paymentId);

            if ($payment->status !== BillingPaymentStatus::PENDING_REVIEW) {
                throw ValidationException::withMessages(['payment_id' => ['لا يمكن رفض دفعة ليست قيد المراجعة.']]);
            }

            $payment->status = BillingPaymentStatus::REJECTED;
            $payment->verified_by = $reviewer->user_id;
            $payment->verified_at = now('Asia/Aden');
            $payment->review_note = $reason;
            $payment->save();

            return $payment->refresh()->load(['invoice', 'currency', 'paymentMethod', 'proofs']);
        }, 3);
    }

    private function startPaidPeriod(BillingSubscription $subscription): void
    {
        $subscription->loadMissing('price');
        $now = now('Asia/Aden');
        $count = max(1, (int) $subscription->price->interval_count);
        $end = match ($subscription->price->billing_interval->value) {
            'DAY' => $now->copy()->addDays($count),
            'WEEK' => $now->copy()->addWeeks($count),
            'MONTH' => $now->copy()->addMonthsNoOverflow($count),
            'YEAR' => $now->copy()->addYearsNoOverflow($count),
            default => throw ValidationException::withMessages(['billing_interval' => ['فترة الاشتراك غير صالحة.']]),
        };

        $subscription->current_period_start = $now;
        $subscription->current_period_end = $end;
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new OverflowException('مجموع الدفعات يتجاوز السعة الرقمية المسموحة.');
        }

        return $left + $right;
    }
}
