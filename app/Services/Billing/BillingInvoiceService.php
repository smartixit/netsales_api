<?php

namespace App\Services\Billing;

use App\Enums\BillingInvoiceStatus;
use App\Enums\LedgerEntryStatus;
use App\Models\{BillingInvoice, BillingSubscription, Currency, LedgerJournalEntry, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OverflowException;

class BillingInvoiceService
{
    public function __construct(
        private BillingLedgerService $ledger,
        private BillingLedgerChartService $chart,
        private BillingAuthorizationService $authorization
    ) {}

    public function createForSubscription(
        BillingSubscription $subscription,
        ?User $actor = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        bool $includeSetupFee = true
    ): BillingInvoice {
        return DB::transaction(function () use ($subscription, $actor, $periodStart, $periodEnd, $includeSetupFee): BillingInvoice {
            $subscription->loadMissing([
                'billingAccount.defaultCurrency',
                'plan.product',
                'planVersion.features',
                'price.currency',
            ]);

            $currency = $subscription->currency;
            if (! $currency || (int) $currency->currency_id !== (int) $subscription->price->currency_id) {
                throw ValidationException::withMessages(['currency_id' => ['عملة الاشتراك لا تطابق عملة السعر.']]);
            }

            if ((int) $subscription->billingAccount->default_currency_id !== (int) $currency->currency_id) {
                throw ValidationException::withMessages(['currency_id' => ['عملة السعر لا تطابق العملة التعاقدية لحساب الفوترة.']]);
            }

            $price = $subscription->price;
            $subtotal = $this->multiplyAmount((int) $price->unit_amount_minor, (int) $subscription->quantity);
            $setupFee = $includeSetupFee ? (int) $price->setup_fee_minor : 0;
            $lineSubtotal = $this->safeAdd($subtotal, $setupFee);
            $periodStart ??= $subscription->current_period_start?->toDateString();
            $periodEnd ??= $subscription->current_period_end?->toDateString();

            $invoice = BillingInvoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'billing_account_id' => $subscription->billing_account_id,
                'billing_subscription_id' => $subscription->billing_subscription_id,
                'currency_id' => $currency->currency_id,
                'invoice_type' => 'STANDARD',
                'status' => BillingInvoiceStatus::DRAFT,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'subtotal_minor' => $lineSubtotal,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $lineSubtotal,
                'paid_minor' => 0,
                'remaining_minor' => $lineSubtotal,
                'customer_snapshot' => [
                    'billing_account_id' => $subscription->billing_account_id,
                    'account_number' => $subscription->billingAccount->account_number,
                    'legal_name' => $subscription->billingAccount->legal_name,
                    'billing_email' => $subscription->billingAccount->billing_email,
                    'billing_address' => $subscription->billingAccount->billing_address,
                ],
                'pricing_snapshot' => [
                    'plan_id' => $subscription->plan_id,
                    'plan_version_id' => $subscription->plan_version_id,
                    'price_id' => $price->price_id,
                    'currency_code' => $currency->code,
                    'decimal_places' => $currency->decimal_places,
                    'billing_interval' => $price->billing_interval->value,
                    'interval_count' => $price->interval_count,
                    'quantity' => $subscription->quantity,
                    'unit_amount_minor' => $price->unit_amount_minor,
                    'setup_fee_minor' => $price->setup_fee_minor,
                    'tax_behavior' => $price->tax_behavior,
                ],
                'due_on' => now('Asia/Aden')->addDays((int) $subscription->billingAccount->payment_terms_days)->toDateString(),
                'created_by' => $actor?->user_id,
            ]);

            $description = $subscription->plan->name.' - '.$price->billing_interval->label();
            $invoice->lines()->create([
                'currency_id' => $currency->currency_id,
                'line_type' => 'SUBSCRIPTION',
                'description' => $description,
                'quantity' => $subscription->quantity,
                'unit_amount_minor' => $price->unit_amount_minor,
                'subtotal_minor' => $subtotal,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $subtotal,
                'plan_version_id' => $subscription->plan_version_id,
                'price_id' => $price->price_id,
                'source_snapshot' => $invoice->pricing_snapshot,
            ]);

            if ($setupFee > 0) {
                $invoice->lines()->create([
                    'currency_id' => $currency->currency_id,
                    'line_type' => 'SETUP_FEE',
                    'description' => 'رسوم التفعيل - '.$subscription->plan->name,
                    'quantity' => 1,
                    'unit_amount_minor' => $setupFee,
                    'subtotal_minor' => $setupFee,
                    'discount_minor' => 0,
                    'tax_minor' => 0,
                    'total_minor' => $setupFee,
                    'plan_version_id' => $subscription->plan_version_id,
                    'price_id' => $price->price_id,
                    'source_snapshot' => ['setup_fee_minor' => $setupFee],
                ]);
            }

            $this->appendMeteredUsageLines($invoice, $subscription, $periodStart, $periodEnd);

            return $invoice->load(['lines.currency', 'currency', 'billingAccount']);
        }, 3);
    }

    public function finalize(int $invoiceId, ?User $actor = null): BillingInvoice
    {
        return DB::transaction(function () use ($invoiceId, $actor): BillingInvoice {
            $invoice = BillingInvoice::query()
                ->with(['lines', 'currency'])
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->status !== BillingInvoiceStatus::DRAFT) {
                if (in_array($invoice->status, [BillingInvoiceStatus::OPEN, BillingInvoiceStatus::PARTIALLY_PAID, BillingInvoiceStatus::PARTIALLY_CREDITED, BillingInvoiceStatus::PAID, BillingInvoiceStatus::CREDITED], true)) {
                    return $invoice->load(['lines', 'currency']);
                }

                throw ValidationException::withMessages(['invoice_id' => ['لا يمكن إصدار هذه الفاتورة في حالتها الحالية.']]);
            }

            if ($invoice->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => ['لا يمكن إصدار فاتورة دون بنود.']]);
            }

            $subtotal = 0;
            $discount = 0;
            $tax = 0;
            $total = 0;

            foreach ($invoice->lines as $line) {
                if ((int) $line->currency_id !== (int) $invoice->currency_id) {
                    throw ValidationException::withMessages(['currency_id' => ['جميع بنود الفاتورة يجب أن تستخدم عملة الفاتورة نفسها.']]);
                }

                $subtotal = $this->safeAdd($subtotal, (int) $line->subtotal_minor);
                $discount = $this->safeAdd($discount, (int) $line->discount_minor);
                $tax = $this->safeAdd($tax, (int) $line->tax_minor);
                $lineTotal = $this->safeAdd((int) $line->subtotal_minor - (int) $line->discount_minor, (int) $line->tax_minor);

                if ($lineTotal < 0 || $lineTotal !== (int) $line->total_minor) {
                    throw ValidationException::withMessages(['lines' => ['يوجد بند فاتورة لا تتطابق قيمه المحفوظة مع إجماليه.']]);
                }

                $total = $this->safeAdd($total, $lineTotal);
            }

            if ($total < 0) {
                throw ValidationException::withMessages(['total_minor' => ['لا يمكن إصدار فاتورة بقيمة سالبة.']]);
            }

            $invoice->subtotal_minor = $subtotal;
            $invoice->discount_minor = $discount;
            $invoice->tax_minor = $tax;
            $invoice->total_minor = $total;
            $invoice->paid_minor = 0;
            $invoice->remaining_minor = $total;
            $invoice->issued_on = now('Asia/Aden')->toDateString();
            $invoice->finalized_at = now('Asia/Aden');

            if ($total === 0) {
                $invoice->status = BillingInvoiceStatus::PAID;
                $invoice->paid_at = now('Asia/Aden');
                $invoice->memo = trim(($invoice->memo ?? '')."\nتم إغلاق الفاتورة كفاتورة صفرية؛ لا يوجد مبلغ مستحق للتحصيل.");
                $invoice->save();

                return $invoice->load(['lines', 'currency', 'billingAccount']);
            }

            $accounts = $this->chart->ensureForCurrency($invoice->currency);
            $invoice->subtotal_minor = $subtotal;
            $invoice->discount_minor = $discount;
            $invoice->tax_minor = $tax;
            $invoice->total_minor = $total;
            $invoice->paid_minor = 0;
            $invoice->remaining_minor = $total;
            $invoice->issued_on = now('Asia/Aden')->toDateString();
            $invoice->finalized_at = now('Asia/Aden');
            $invoice->status = BillingInvoiceStatus::OPEN;
            $invoice->save();

            $this->ledger->post(
                (int) $invoice->currency_id,
                'إصدار فاتورة اشتراك '.$invoice->invoice_number,
                'billing-invoice-issued-'.$invoice->invoice_id,
                [
                    ['ledger_account_id' => $accounts['1100']->ledger_account_id, 'debit_minor' => $total, 'credit_minor' => 0],
                    ['ledger_account_id' => $accounts['2100']->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $total],
                ],
                $actor,
                'BILLING_INVOICE',
                (int) $invoice->invoice_id
            );

            return $invoice->load(['lines', 'currency', 'billingAccount']);
        }, 3);
    }

    public function void(int $invoiceId, string $reason, User $actor): BillingInvoice
    {
        $this->authorization->assertPlatform($actor);

        return DB::transaction(function () use ($invoiceId, $reason, $actor): BillingInvoice {
            $invoice = BillingInvoice::query()->with('currency')->lockForUpdate()->findOrFail($invoiceId);

            if (! in_array($invoice->status, [BillingInvoiceStatus::DRAFT, BillingInvoiceStatus::OPEN], true)
                || (int) $invoice->paid_minor !== 0) {
                throw ValidationException::withMessages(['invoice_id' => ['لا يمكن إلغاء فاتورة مدفوعة أو مسددة جزئياً أو سبق إغلاقها.']]);
            }

            $hasPendingPayments = \App\Models\BillingPayment::query()
                ->where('invoice_id', $invoice->invoice_id)
                ->where('status', \App\Enums\BillingPaymentStatus::PENDING_REVIEW)
                ->exists();

            if ($hasPendingPayments) {
                throw ValidationException::withMessages([
                    'invoice_id' => ['لا يمكن إلغاء الفاتورة بينما توجد طلبات دفع قيد المراجعة؛ ارفضها أو احسمها أولاً.'],
                ]);
            }

            if ($invoice->status === BillingInvoiceStatus::OPEN) {
                $entry = LedgerJournalEntry::query()
                    ->where('source_type', 'BILLING_INVOICE')
                    ->where('source_id', $invoice->invoice_id)
                    ->where('status', LedgerEntryStatus::POSTED)
                    ->first();

                if ($entry) {
                    $this->ledger->reverse(
                        (int) $entry->journal_entry_id,
                        $reason,
                        'billing-invoice-void-'.$invoice->invoice_id,
                        $actor
                    );
                }
            }

            $invoice->status = BillingInvoiceStatus::VOID;
            $invoice->voided_at = now('Asia/Aden');
            $invoice->memo = trim(($invoice->memo ?? '')."\nإلغاء: ".$reason);
            $invoice->save();

            return $invoice->load(['lines', 'currency']);
        }, 3);
    }

    private function appendMeteredUsageLines(
        BillingInvoice $invoice,
        BillingSubscription $subscription,
        ?string $periodStart,
        ?string $periodEnd
    ): void {
        if ($periodStart === null || $periodEnd === null) {
            return;
        }

        $aggregates = \App\Models\BillingUsageAggregate::query()
            ->with('meter')
            ->where('billing_subscription_id', $subscription->billing_subscription_id)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->where('amount_minor', '>', 0)
            ->get();

        foreach ($aggregates as $aggregate) {
            $amount = (int) $aggregate->amount_minor;
            $invoice->lines()->create([
                'currency_id' => $invoice->currency_id,
                'line_type' => 'METERED_USAGE',
                'description' => 'استهلاك إضافي: '.($aggregate->meter?->name ?? $aggregate->meter?->meter_key ?? 'Metered usage'),
                'quantity' => 1,
                'unit_amount_minor' => $amount,
                'subtotal_minor' => $amount,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $amount,
                'source_snapshot' => [
                    'meter_id' => $aggregate->meter_id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'quantity' => (string) $aggregate->quantity,
                    'unit' => $aggregate->meter?->unit,
                    'amount_minor' => $amount,
                ],
            ]);
        }
    }

    private function nextInvoiceNumber(): string
    {
        return 'NS-INV-'.now('Asia/Aden')->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    private function multiplyAmount(int $amount, int $quantity): int
    {
        if ($amount < 0 || $quantity < 1 || ($quantity > 0 && $amount > intdiv(PHP_INT_MAX, $quantity))) {
            throw new OverflowException('مبلغ بند الفاتورة يتجاوز الحدود الرقمية المسموحة.');
        }

        return $amount * $quantity;
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new OverflowException('إجمالي الفاتورة يتجاوز الحدود الرقمية المسموحة.');
        }

        if ($right < 0 && $left < PHP_INT_MIN - $right) {
            throw new OverflowException('قيمة الخصم تتجاوز الحدود الرقمية المسموحة.');
        }

        return $left + $right;
    }
}
