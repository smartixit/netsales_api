<?php

namespace App\Services\Billing;

use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingPaymentStatus;
use App\Models\{BillingCreditNote, BillingInvoice, BillingInvoiceLine, Currency, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OverflowException;

class BillingCreditNoteService
{
    public function __construct(
        private BillingAuthorizationService $authorization,
        private BillingLedgerService $ledger,
        private BillingLedgerChartService $chart
    ) {}

    /**
     * Issues a credit note, offsets outstanding receivables first, and reserves only
     * the remaining amount as a refundable customer credit.
     *
     * @param array<int, array{invoice_line_id:int, amount_minor:int}> $lines
     */
    public function issue(
        int $invoiceId,
        array $lines,
        string $reason,
        string $accountingTreatment,
        string $idempotencyKey,
        User $actor
    ): BillingCreditNote {
        $this->authorization->assertPlatform($actor);

        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مطلوب.']]);
        }

        if (! in_array($accountingTreatment, ['DEFERRED_REVENUE', 'REVENUE_REVERSAL'], true)) {
            throw ValidationException::withMessages(['accounting_treatment' => ['المعالجة المحاسبية للإشعار الدائن غير صالحة.']]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => ['يجب أن يحتوي الإشعار الدائن على بند واحد على الأقل.']]);
        }

        return DB::transaction(function () use (
            $invoiceId,
            $lines,
            $reason,
            $accountingTreatment,
            $idempotencyKey,
            $actor
        ): BillingCreditNote {
            $invoice = BillingInvoice::query()
                ->with('currency')
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            $existing = BillingCreditNote::query()
                ->where('idempotency_key', $idempotencyKey)
                ->with(['lines', 'currency', 'invoice'])
                ->first();

            if ($existing) {
                $this->assertSameRequest($existing, $invoiceId, $lines, $reason, $accountingTreatment);
                return $existing;
            }

            if ($invoice->finalized_at === null || in_array($invoice->status, [
                BillingInvoiceStatus::DRAFT,
                BillingInvoiceStatus::VOID,
                BillingInvoiceStatus::UNCOLLECTIBLE,
                BillingInvoiceStatus::CREDITED,
            ], true)) {
                throw ValidationException::withMessages(['invoice_id' => ['لا يمكن إصدار إشعار دائن لهذه الفاتورة في حالتها الحالية.']]);
            }

            $normalized = collect($lines)
                ->map(fn (array $line): array => [
                    'invoice_line_id' => (int) ($line['invoice_line_id'] ?? 0),
                    'amount_minor' => (int) ($line['amount_minor'] ?? 0),
                ])
                ->sortBy('invoice_line_id')
                ->values();

            if ($normalized->contains(fn (array $line): bool => $line['invoice_line_id'] <= 0 || $line['amount_minor'] <= 0)
                || $normalized->pluck('invoice_line_id')->unique()->count() !== $normalized->count()) {
                throw ValidationException::withMessages(['lines' => ['بنود الإشعار الدائن غير صالحة أو تحتوي على بند مكرر.']]);
            }

            $lineIds = $normalized->pluck('invoice_line_id')->all();
            $invoiceLines = BillingInvoiceLine::query()
                ->where('invoice_id', $invoice->invoice_id)
                ->whereIn('invoice_line_id', $lineIds)
                ->orderBy('invoice_line_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('invoice_line_id');

            if ($invoiceLines->count() !== count($lineIds)) {
                throw ValidationException::withMessages(['lines' => ['يجب أن تنتمي جميع البنود إلى الفاتورة المحددة.']]);
            }

            $previousByLine = DB::table('billing_credit_note_lines')
                ->join('billing_credit_notes', 'billing_credit_notes.credit_note_id', '=', 'billing_credit_note_lines.credit_note_id')
                ->where('billing_credit_notes.invoice_id', $invoice->invoice_id)
                ->whereIn('billing_credit_notes.status', ['ISSUED', 'PARTIALLY_REFUNDED', 'REFUNDED'])
                ->whereIn('billing_credit_note_lines.invoice_line_id', $lineIds)
                ->groupBy('billing_credit_note_lines.invoice_line_id')
                ->selectRaw('billing_credit_note_lines.invoice_line_id, SUM(billing_credit_note_lines.amount_minor) AS credited_minor')
                ->pluck('credited_minor', 'invoice_line_id');

            $amountMinor = 0;
            foreach ($normalized as $line) {
                $source = $invoiceLines->get($line['invoice_line_id']);
                if ((int) $source->currency_id !== (int) $invoice->currency_id) {
                    throw ValidationException::withMessages(['currency_id' => ['عملة بند الإشعار الدائن لا تطابق عملة الفاتورة.']]);
                }

                $alreadyCredited = (int) ($previousByLine[$line['invoice_line_id']] ?? 0);
                $availableOnLine = (int) $source->total_minor - $alreadyCredited;
                if ($line['amount_minor'] > $availableOnLine) {
                    throw ValidationException::withMessages([
                        'lines' => ['قيمة أحد بنود الإشعار تتجاوز الرصيد غير المعتمد من بند الفاتورة الأصلي.'],
                    ]);
                }

                $amountMinor = $this->safeAdd($amountMinor, $line['amount_minor']);
            }

            $previousCreditTotal = (int) BillingCreditNote::query()
                ->where('invoice_id', $invoice->invoice_id)
                ->whereIn('status', ['ISSUED', 'PARTIALLY_REFUNDED', 'REFUNDED'])
                ->sum('total_minor');

            if ($amountMinor > (int) $invoice->total_minor - $previousCreditTotal) {
                throw ValidationException::withMessages(['amount_minor' => ['إجمالي الإشعارات الدائنة سيتجاوز قيمة الفاتورة الأصلية.']]);
            }

            $collectedMinor = (int) DB::table('billing_payments')
                ->where('invoice_id', $invoice->invoice_id)
                ->whereIn('status', [
                    BillingPaymentStatus::VERIFIED->value,
                    BillingPaymentStatus::PARTIALLY_REFUNDED->value,
                    BillingPaymentStatus::REFUNDED->value,
                ])
                ->sum('amount_minor');

            $refundedMinor = (int) DB::table('billing_refunds')
                ->join('billing_payments', 'billing_payments.payment_id', '=', 'billing_refunds.payment_id')
                ->where('billing_payments.invoice_id', $invoice->invoice_id)
                ->where('billing_refunds.status', 'COMPLETED')
                ->sum('billing_refunds.amount_minor');

            $reservedRefundableMinor = (int) BillingCreditNote::query()
                ->where('invoice_id', $invoice->invoice_id)
                ->whereIn('status', ['ISSUED', 'PARTIALLY_REFUNDED'])
                ->selectRaw('COALESCE(SUM(refundable_minor - refunded_minor), 0) AS reserved_minor')
                ->value('reserved_minor');

            $availableCollectedMinor = max(0, $collectedMinor - $refundedMinor - $reservedRefundableMinor);
            $appliedToReceivableMinor = min($amountMinor, (int) $invoice->remaining_minor);
            $refundableMinor = $amountMinor - $appliedToReceivableMinor;

            if ($refundableMinor > $availableCollectedMinor) {
                throw ValidationException::withMessages([
                    'amount_minor' => ['الجزء القابل للاسترداد يتجاوز صافي المبالغ المحصلة وغير المحجوزة لإشعارات دائنة سابقة.'],
                ]);
            }

            $note = BillingCreditNote::query()->create([
                'credit_note_number' => 'NS-CN-'.now('Asia/Aden')->format('Ymd').'-'.Str::upper(Str::random(8)),
                'idempotency_key' => $idempotencyKey,
                'invoice_id' => $invoice->invoice_id,
                'billing_account_id' => $invoice->billing_account_id,
                'currency_id' => $invoice->currency_id,
                'status' => 'DRAFT',
                'reason' => $reason,
                'accounting_treatment' => $accountingTreatment,
                'subtotal_minor' => $amountMinor,
                'tax_minor' => 0,
                'total_minor' => $amountMinor,
                'applied_to_receivable_minor' => $appliedToReceivableMinor,
                'refundable_minor' => $refundableMinor,
                'refunded_minor' => 0,
                'created_by' => $actor->user_id,
            ]);

            foreach ($normalized as $line) {
                $source = $invoiceLines->get($line['invoice_line_id']);
                $note->lines()->create([
                    'invoice_line_id' => $source->invoice_line_id,
                    'description' => $source->description,
                    'quantity_micros' => 1_000_000,
                    'amount_minor' => $line['amount_minor'],
                ]);
            }

            $accounts = $this->chart->ensureForCurrency($invoice->currency);
            // Reverse the liability for unearned service, or debit subscription revenue for earned service.
            $debitCode = $accountingTreatment === 'DEFERRED_REVENUE' ? '2100' : '4100';

            $this->ledger->post(
                (int) $invoice->currency_id,
                'إصدار إشعار دائن '.$note->credit_note_number.' للفاتورة '.$invoice->invoice_number,
                'billing-credit-note-issued-'.$note->credit_note_id,
                [
                    ['ledger_account_id' => $accounts[$debitCode]->ledger_account_id, 'debit_minor' => $amountMinor, 'credit_minor' => 0],
                    ['ledger_account_id' => $accounts['1100']->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $appliedToReceivableMinor],
                    ['ledger_account_id' => $accounts['2200']->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $refundableMinor],
                ],
                $actor,
                'BILLING_CREDIT_NOTE',
                (int) $note->credit_note_id
            );

            $note->status = 'ISSUED';
            $note->issued_at = now('Asia/Aden');
            $note->save();

            $invoice->credited_minor = (int) $invoice->credited_minor + $amountMinor;
            $invoice->remaining_minor = max(0, (int) $invoice->total_minor - (int) $invoice->paid_minor - (int) $invoice->credited_minor);
            $newCreditTotal = $previousCreditTotal + $amountMinor;

            if ($invoice->remaining_minor === 0) {
                $invoice->status = (int) $invoice->credited_minor >= (int) $invoice->total_minor
                    ? BillingInvoiceStatus::CREDITED
                    : BillingInvoiceStatus::PAID;
                if ($invoice->status === BillingInvoiceStatus::PAID && $invoice->paid_at === null) {
                    $invoice->paid_at = now('Asia/Aden');
                }
            } elseif ((int) $invoice->credited_minor > 0) {
                $invoice->status = BillingInvoiceStatus::PARTIALLY_CREDITED;
            } else {
                $invoice->status = (int) $invoice->paid_minor > 0
                    ? BillingInvoiceStatus::PARTIALLY_PAID
                    : BillingInvoiceStatus::OPEN;
            }

            $invoice->memo = trim(($invoice->memo ?? '')."\nإشعار دائن {$note->credit_note_number}: {$amountMinor} بوحدة العملة الصغرى.");
            $invoice->save();

            return $note->refresh()->load(['invoice', 'currency', 'lines.invoiceLine']);
        }, 3);
    }

    private function assertSameRequest(
        BillingCreditNote $existing,
        int $invoiceId,
        array $lines,
        string $reason,
        string $accountingTreatment
    ): void {
        $expected = collect($lines)->map(fn (array $line): array => [
            'invoice_line_id' => (int) ($line['invoice_line_id'] ?? 0),
            'amount_minor' => (int) ($line['amount_minor'] ?? 0),
        ])->sortBy('invoice_line_id')->values()->all();

        $actual = $existing->lines->map(fn ($line): array => [
            'invoice_line_id' => (int) $line->invoice_line_id,
            'amount_minor' => (int) $line->amount_minor,
        ])->sortBy('invoice_line_id')->values()->all();

        if ((int) $existing->invoice_id !== $invoiceId
            || $existing->reason !== $reason
            || $existing->accounting_treatment !== $accountingTreatment
            || $expected !== $actual) {
            throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مستخدم لإشعار دائن ببيانات مختلفة.']]);
        }
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new OverflowException('إجمالي الإشعار الدائن يتجاوز السعة الرقمية المسموحة.');
        }

        return $left + $right;
    }
}
