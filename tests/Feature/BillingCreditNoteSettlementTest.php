<?php

namespace Tests\Feature;

use App\Enums\{BillingInvoiceStatus, NetworkStatus};
use App\Models\{
    BillingAccount,
    BillingCreditNote,
    BillingInvoice,
    BillingInvoiceLine,
    BillingPaymentMethod,
    BillingRevenueRecognitionSchedule,
    Currency,
    LedgerJournalEntry,
    LedgerJournalLine,
    Network,
    TreasuryAccount,
    User
};
use App\Services\Billing\{
    BillingCreditNoteService,
    BillingLedgerChartService,
    BillingLedgerService,
    BillingPaymentService,
    BillingRefundService
};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingCreditNoteSettlementTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    private Currency $currency;

    private BillingAccount $account;

    private BillingInvoice $invoice;

    private BillingInvoiceLine $line;

    private array $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', 'SUPER_ADMIN'))
            ->firstOrFail();
        $this->currency = Currency::query()->where('code', 'YER')->firstOrFail();

        $network = Network::query()->create([
            'owner_id' => $this->actor->user_id,
            'network_uuid' => (string) Str::uuid(),
            'network_name' => 'Credit Note Test '.uniqid(),
            'network_phone_number' => '73'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->account = BillingAccount::query()->create([
            'network_id' => $network->network_id,
            'account_number' => 'BA-'.Str::upper(Str::random(16)),
            'legal_name' => 'Credit Note Settlement Test',
            'default_currency_id' => $this->currency->currency_id,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_by' => $this->actor->user_id,
        ]);

        $this->accounts = app(BillingLedgerChartService::class)->ensureForCurrency($this->currency);
    }

    public function test_credit_note_reverses_unearned_and_earned_subscription_revenue_automatically(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);
        $schedule = BillingRevenueRecognitionSchedule::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'invoice_line_id' => $this->line->invoice_line_id,
            'currency_id' => $this->currency->currency_id,
            'revenue_account_code' => '4100',
            'total_minor' => 1000,
            'recognized_minor' => 300,
            'service_start_on' => '2026-01-01',
            'service_end_on' => '2026-01-10',
            'last_recognized_on' => '2026-01-03',
            'status' => 'ACTIVE',
        ]);

        $note = app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 800]],
            'إلغاء جزء من خدمة الاشتراك',
            'credit-note-subscription-'.Str::uuid(),
            $this->actor
        );

        $this->assertSame('AUTOMATIC', $note->accounting_treatment);
        $this->assertSame(800, $note->total_minor);
        $this->assertSame(800, $note->applied_to_receivable_minor);
        $this->assertSame(0, $note->refundable_minor);
        $schedule->refresh();
        $this->assertSame(200, $schedule->total_minor);
        $this->assertSame(200, $schedule->recognized_minor);
        $this->assertSame('COMPLETED', $schedule->status);

        $this->invoice->refresh();
        $this->assertSame(800, $this->invoice->credited_minor);
        $this->assertSame(200, $this->invoice->remaining_minor);
        $this->assertSame(BillingInvoiceStatus::PARTIALLY_CREDITED, $this->invoice->status);

        $entry = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_CREDIT_NOTE')
            ->where('source_id', $note->credit_note_id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertLedgerEntryBalanced($entry);
        $this->assertLedgerAmount($entry, '2100', 'debit_minor', 700);
        $this->assertLedgerAmount($entry, '4100', 'debit_minor', 100);
        $this->assertLedgerAmount($entry, '1100', 'credit_minor', 800);
    }

    public function test_credit_note_allocates_tax_and_setup_revenue_without_manual_treatment(): void
    {
        $this->createInvoiceLine('SETUP_FEE', 1000, 100, 1100);

        $note = app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 550]],
            'تخفيض جزئي لرسوم التهيئة',
            'credit-note-tax-'.Str::uuid(),
            $this->actor
        );

        $this->assertSame('AUTOMATIC', $note->accounting_treatment);
        $this->assertSame(500, $note->subtotal_minor);
        $this->assertSame(50, $note->tax_minor);
        $this->assertSame(550, $note->total_minor);

        $entry = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_CREDIT_NOTE')
            ->where('source_id', $note->credit_note_id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertLedgerEntryBalanced($entry);
        $this->assertLedgerAmount($entry, '4120', 'debit_minor', 500);
        $this->assertLedgerAmount($entry, '2300', 'debit_minor', 50);
        $this->assertLedgerAmount($entry, '1100', 'credit_minor', 550);
    }

    public function test_full_customer_refund_settles_customer_credit_liability_against_treasury(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);
        BillingRevenueRecognitionSchedule::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'invoice_line_id' => $this->line->invoice_line_id,
            'currency_id' => $this->currency->currency_id,
            'revenue_account_code' => '4100',
            'total_minor' => 1000,
            'recognized_minor' => 0,
            'service_start_on' => '2026-01-01',
            'service_end_on' => '2026-01-10',
            'status' => 'PENDING',
        ]);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $treasury = TreasuryAccount::query()->create([
            'account_code' => 'TR-TEST-'.Str::upper(Str::random(10)),
            'name' => 'Test Treasury',
            'account_type' => 'CASH',
            'currency_id' => $this->currency->currency_id,
            'status' => 'ACTIVE',
        ]);

        $paymentService = app(BillingPaymentService::class);
        $payment = $paymentService->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            1000,
            'payment-'.Str::uuid(),
            $this->actor
        );
        $payment = $paymentService->verify(
            (int) $payment->payment_id,
            (int) $treasury->treasury_account_id,
            $this->actor
        );

        $this->assertSame('VERIFIED', $payment->status->value);
        $this->invoice->refresh();
        $this->assertSame(BillingInvoiceStatus::PAID, $this->invoice->status);

        $note = app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 1000]],
            'استرداد كامل بعد إلغاء الاشتراك',
            'credit-note-refund-'.Str::uuid(),
            $this->actor
        );

        $this->assertSame(0, $note->applied_to_receivable_minor);
        $this->assertSame(1000, $note->refundable_minor);

        $treasury->refresh()->load('ledgerAccount');

        $refund = app(BillingRefundService::class)->refund(
            (int) $payment->payment_id,
            (int) $note->credit_note_id,
            (int) $treasury->treasury_account_id,
            1000,
            'رد كامل إلى العميل',
            'refund-'.Str::uuid(),
            $this->actor
        );

        $this->assertSame('COMPLETED', $refund->status);
        $payment->refresh();
        $note->refresh();
        $this->assertSame('REFUNDED', $payment->status->value);
        $this->assertSame('REFUNDED', $note->status);

        $refundEntry = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_REFUND')
            ->where('source_id', $refund->refund_id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertLedgerEntryBalanced($refundEntry);
        $this->assertLedgerAmount($refundEntry, '2200', 'debit_minor', 1000);
        $this->assertLedgerAmount($refundEntry, $treasury->ledgerAccount->account_code ?? '', 'credit_minor', 1000);
    }

    private function createInvoiceLine(string $type, int $subtotal, int $tax, int $total): void
    {
        $this->invoice = BillingInvoice::query()->create([
            'invoice_number' => 'INV-'.Str::upper(Str::random(16)),
            'billing_account_id' => $this->account->billing_account_id,
            'currency_id' => $this->currency->currency_id,
            'invoice_type' => 'STANDARD',
            'status' => BillingInvoiceStatus::DRAFT,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-10',
            'subtotal_minor' => $subtotal,
            'discount_minor' => 0,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'paid_minor' => 0,
            'credited_minor' => 0,
            'remaining_minor' => $total,
            'customer_snapshot' => ['billing_account_id' => $this->account->billing_account_id],
            'pricing_snapshot' => ['currency_code' => $this->currency->code],
            'created_by' => $this->actor->user_id,
        ]);

        $this->line = BillingInvoiceLine::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'currency_id' => $this->currency->currency_id,
            'line_type' => $type,
            'description' => $type.' billing line',
            'quantity' => 1,
            'unit_amount_minor' => $subtotal,
            'subtotal_minor' => $subtotal,
            'discount_minor' => 0,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'source_snapshot' => ['billing_interval' => 'MONTH', 'interval_count' => 1],
        ]);

        $this->invoice->status = BillingInvoiceStatus::OPEN;
        $this->invoice->finalized_at = now('Asia/Aden');
        $this->invoice->save();

        $lines = [
            ['ledger_account_id' => $this->accounts['1100']->ledger_account_id, 'debit_minor' => $total, 'credit_minor' => 0],
        ];

        $accountCode = match ($type) {
            'SUBSCRIPTION' => '2100',
            'SETUP_FEE' => '4120',
            'METERED_USAGE' => '4110',
            default => throw new \LogicException('Unexpected test invoice line type.'),
        };

        if ($subtotal > 0) {
            $lines[] = ['ledger_account_id' => $this->accounts[$accountCode]->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $subtotal];
        }
        if ($tax > 0) {
            $lines[] = ['ledger_account_id' => $this->accounts['2300']->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $tax];
        }

        app(BillingLedgerService::class)->post(
            (int) $this->currency->currency_id,
            'Initial invoice ledger posting',
            'test-invoice-'.$this->invoice->invoice_id,
            $lines,
            $this->actor,
            'BILLING_INVOICE',
            (int) $this->invoice->invoice_id
        );
    }

    private function assertLedgerEntryBalanced(LedgerJournalEntry $entry): void
    {
        $this->assertSame((int) $entry->lines->sum('debit_minor'), (int) $entry->lines->sum('credit_minor'));
    }

    private function assertLedgerAmount(LedgerJournalEntry $entry, string $accountCode, string $side, int $expected): void
    {
        $actual = (int) $entry->lines
            ->filter(fn (LedgerJournalLine $line): bool => $line->account?->account_code === $accountCode)
            ->sum($side);
        $this->assertSame($expected, $actual, "Unexpected {$side} balance for ledger account {$accountCode}.");
    }
}
