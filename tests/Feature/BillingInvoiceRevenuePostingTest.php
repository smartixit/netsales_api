<?php

namespace Tests\Feature;

use App\Enums\{BillingInvoiceStatus, NetworkStatus};
use App\Models\{
    BillingAccount,
    BillingInvoice,
    BillingInvoiceLine,
    BillingRevenueRecognitionSchedule,
    Currency,
    LedgerJournalEntry,
    Network,
    User
};
use App\Services\Billing\{
    BillingInvoiceService,
    BillingLedgerChartService,
    BillingRevenueRecognitionService
};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingInvoiceRevenuePostingTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    private Currency $currency;

    private BillingAccount $account;

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
            'network_name' => 'Invoice Recognition Test '.uniqid(),
            'network_phone_number' => '73'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->account = BillingAccount::query()->create([
            'network_id' => $network->network_id,
            'account_number' => 'BA-'.Str::upper(Str::random(16)),
            'legal_name' => 'Invoice Recognition Test',
            'default_currency_id' => $this->currency->currency_id,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_by' => $this->actor->user_id,
        ]);

        app(BillingLedgerChartService::class)->ensureForCurrency($this->currency);
    }

    public function test_invoice_finalization_separates_deferred_earned_and_tax_postings(): void
    {
        $invoice = $this->createDraftInvoice([
            ['type' => 'SUBSCRIPTION', 'subtotal' => 1000, 'tax' => 100, 'interval' => 'MONTH'],
            ['type' => 'SETUP_FEE', 'subtotal' => 500, 'tax' => 50],
            ['type' => 'METERED_USAGE', 'subtotal' => 200, 'tax' => 20],
        ]);

        $finalized = app(BillingInvoiceService::class)->finalize(
            (int) $invoice->invoice_id,
            $this->actor
        );

        $this->assertSame(BillingInvoiceStatus::OPEN, $finalized->status);
        $this->assertSame(1700, $finalized->subtotal_minor);
        $this->assertSame(170, $finalized->tax_minor);
        $this->assertSame(1870, $finalized->total_minor);
        $this->assertSame(1, BillingRevenueRecognitionSchedule::query()
            ->where('invoice_id', $invoice->invoice_id)
            ->count());

        $schedule = BillingRevenueRecognitionSchedule::query()
            ->where('invoice_id', $invoice->invoice_id)
            ->firstOrFail();
        $this->assertSame(1000, $schedule->total_minor);
        $this->assertSame(0, $schedule->recognized_minor);

        $entry = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_INVOICE')
            ->where('source_id', $invoice->invoice_id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertSame((int) $entry->lines->sum('debit_minor'), (int) $entry->lines->sum('credit_minor'));
        $this->assertAccountSideAmount($entry, '1100', 'debit_minor', 1870);
        $this->assertAccountSideAmount($entry, '2100', 'credit_minor', 1000);
        $this->assertAccountSideAmount($entry, '4120', 'credit_minor', 500);
        $this->assertAccountSideAmount($entry, '4110', 'credit_minor', 200);
        $this->assertAccountSideAmount($entry, '2300', 'credit_minor', 170);
    }

    public function test_invoice_voiding_preserves_recognized_revenue_and_cancels_unused_schedule(): void
    {
        $service = app(BillingInvoiceService::class);

        $recognizedInvoice = $this->createDraftInvoice([
            ['type' => 'SUBSCRIPTION', 'subtotal' => 1000, 'tax' => 0, 'interval' => 'MONTH'],
        ]);
        $service->finalize((int) $recognizedInvoice->invoice_id, $this->actor);

        $recognized = app(BillingRevenueRecognitionService::class)->process(
            10,
            CarbonImmutable::parse('2026-01-05', 'Asia/Aden')
        );
        $this->assertSame(1, $recognized['processed']);

        try {
            $service->void((int) $recognizedInvoice->invoice_id, 'Attempt after recognition', $this->actor);
            $this->fail('An invoice with recognized revenue must not be voidable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_id', $exception->errors());
        }

        $unrecognizedInvoice = $this->createDraftInvoice([
            ['type' => 'SUBSCRIPTION', 'subtotal' => 600, 'tax' => 0, 'interval' => 'MONTH'],
        ]);
        $service->finalize((int) $unrecognizedInvoice->invoice_id, $this->actor);
        $voided = $service->void((int) $unrecognizedInvoice->invoice_id, 'No service delivered', $this->actor);

        $this->assertSame(BillingInvoiceStatus::VOID, $voided->status);
        $this->assertSame('CANCELLED', BillingRevenueRecognitionSchedule::query()
            ->where('invoice_id', $unrecognizedInvoice->invoice_id)
            ->value('status'));
    }

    /**
     * @param array<int, array{type:string,subtotal:int,tax:int,interval?:string}> $lines
     */
    private function createDraftInvoice(array $lines): BillingInvoice
    {
        $invoice = BillingInvoice::query()->create([
            'invoice_number' => 'INV-'.Str::upper(Str::random(16)),
            'billing_account_id' => $this->account->billing_account_id,
            'currency_id' => $this->currency->currency_id,
            'invoice_type' => 'STANDARD',
            'status' => BillingInvoiceStatus::DRAFT,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-10',
            'subtotal_minor' => 0,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 0,
            'paid_minor' => 0,
            'credited_minor' => 0,
            'remaining_minor' => 0,
            'customer_snapshot' => ['billing_account_id' => $this->account->billing_account_id],
            'pricing_snapshot' => ['currency_code' => $this->currency->code],
            'created_by' => $this->actor->user_id,
        ]);

        foreach ($lines as $line) {
            $subtotal = $line['subtotal'];
            $tax = $line['tax'];
            $invoice->lines()->create([
                'currency_id' => $this->currency->currency_id,
                'line_type' => $line['type'],
                'description' => $line['type'].' test line',
                'quantity' => 1,
                'unit_amount_minor' => $subtotal,
                'subtotal_minor' => $subtotal,
                'discount_minor' => 0,
                'tax_minor' => $tax,
                'total_minor' => $subtotal + $tax,
                'source_snapshot' => isset($line['interval'])
                    ? ['billing_interval' => $line['interval'], 'interval_count' => 1]
                    : [],
            ]);
        }

        return $invoice->refresh()->load('lines');
    }

    private function assertAccountSideAmount(
        LedgerJournalEntry $entry,
        string $accountCode,
        string $side,
        int $expected
    ): void {
        $actual = (int) $entry->lines
            ->filter(fn ($line): bool => $line->account?->account_code === $accountCode)
            ->sum($side);

        $this->assertSame($expected, $actual, "Unexpected {$side} on ledger account {$accountCode}.");
    }
}
