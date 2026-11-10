<?php

namespace Tests\Feature;

use App\Enums\{BillingInvoiceStatus, NetworkStatus};
use App\Models\{
    BillingAccount,
    BillingInvoice,
    BillingInvoiceLine,
    BillingRevenueRecognitionEntry,
    BillingRevenueRecognitionSchedule,
    Currency,
    LedgerJournalEntry,
    LedgerJournalLine,
    Network,
    User
};
use App\Services\Billing\BillingRevenueRecognitionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingRevenueRecognitionTest extends TestCase
{
    use DatabaseTransactions;

    private Currency $currency;

    private BillingRevenueRecognitionSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::query()->where('code', 'YER')->firstOrFail();
        $user = User::query()->firstOrFail();

        $network = Network::query()->create([
            'owner_id' => $user->user_id,
            'network_uuid' => (string) Str::uuid(),
            'network_name' => 'Billing Revenue Test '.uniqid(),
            'network_phone_number' => '73'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $account = BillingAccount::query()->create([
            'network_id' => $network->network_id,
            'account_number' => 'BA-'.Str::upper(Str::random(16)),
            'legal_name' => 'Revenue Recognition Test',
            'default_currency_id' => $this->currency->currency_id,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_by' => $user->user_id,
        ]);

        $invoice = BillingInvoice::query()->create([
            'invoice_number' => 'INV-'.Str::upper(Str::random(16)),
            'billing_account_id' => $account->billing_account_id,
            'currency_id' => $this->currency->currency_id,
            'invoice_type' => 'STANDARD',
            'status' => BillingInvoiceStatus::DRAFT,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-10',
            'subtotal_minor' => 1001,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 1001,
            'paid_minor' => 0,
            'credited_minor' => 0,
            'remaining_minor' => 1001,
            'customer_snapshot' => ['billing_account_id' => $account->billing_account_id],
            'pricing_snapshot' => ['currency_code' => $this->currency->code],
            'created_by' => $user->user_id,
        ]);

        $line = BillingInvoiceLine::query()->create([
            'invoice_id' => $invoice->invoice_id,
            'currency_id' => $this->currency->currency_id,
            'line_type' => 'SUBSCRIPTION',
            'description' => 'Ten-day subscription period',
            'quantity' => 1,
            'unit_amount_minor' => 1001,
            'subtotal_minor' => 1001,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 1001,
            'source_snapshot' => ['billing_interval' => 'DAY', 'interval_count' => 10],
        ]);

        $this->schedule = BillingRevenueRecognitionSchedule::query()->create([
            'invoice_id' => $invoice->invoice_id,
            'invoice_line_id' => $line->invoice_line_id,
            'currency_id' => $this->currency->currency_id,
            'revenue_account_code' => '4100',
            'total_minor' => 1001,
            'recognized_minor' => 0,
            'service_start_on' => '2026-01-01',
            'service_end_on' => '2026-01-10',
            'status' => 'PENDING',
        ]);
    }

    public function test_recognizes_revenue_proportionally_and_posts_balanced_entries(): void
    {
        $service = app(BillingRevenueRecognitionService::class);

        $first = $service->process(10, CarbonImmutable::parse('2026-01-05', 'Asia/Aden'));

        $this->assertSame(1, $first['processed']);
        $this->assertSame(500, $first['amount_by_currency'][$this->currency->currency_id]);
        $this->schedule->refresh();
        $this->assertSame(500, $this->schedule->recognized_minor);
        $this->assertSame('ACTIVE', $this->schedule->status);

        $second = $service->process(10, CarbonImmutable::parse('2026-01-10', 'Asia/Aden'));

        $this->assertSame(1, $second['processed']);
        $this->assertSame(501, $second['amount_by_currency'][$this->currency->currency_id]);
        $this->schedule->refresh();
        $this->assertSame(1001, $this->schedule->recognized_minor);
        $this->assertSame('COMPLETED', $this->schedule->status);

        $entries = BillingRevenueRecognitionEntry::query()
            ->where('revenue_schedule_id', $this->schedule->revenue_schedule_id)
            ->with('journalEntry.lines')
            ->orderBy('revenue_recognition_entry_id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame(1001, (int) $entries->sum('amount_minor'));

        foreach ($entries as $entry) {
            $debits = (int) $entry->journalEntry->lines->sum('debit_minor');
            $credits = (int) $entry->journalEntry->lines->sum('credit_minor');
            $this->assertSame($debits, $credits);
            $this->assertSame((int) $entry->amount_minor, $debits);
        }

        $this->assertSame(2, LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_REVENUE_RECOGNITION')
            ->where('source_id', $this->schedule->revenue_schedule_id)
            ->count());
    }

    public function test_recognition_is_idempotent_for_repeated_scheduler_runs_on_same_date(): void
    {
        $service = app(BillingRevenueRecognitionService::class);
        $asOf = CarbonImmutable::parse('2026-01-05', 'Asia/Aden');

        $first = $service->process(10, $asOf);
        $second = $service->process(10, $asOf);

        $this->assertSame(1, $first['processed']);
        $this->assertSame(0, $second['processed']);
        $this->assertSame(1, BillingRevenueRecognitionEntry::query()
            ->where('revenue_schedule_id', $this->schedule->revenue_schedule_id)
            ->count());

        $this->schedule->refresh();
        $this->assertSame(500, $this->schedule->recognized_minor);
    }

    public function test_recognition_waits_until_service_start_and_catches_up_after_end(): void
    {
        $service = app(BillingRevenueRecognitionService::class);

        $beforeStart = $service->process(10, CarbonImmutable::parse('2025-12-31', 'Asia/Aden'));
        $this->assertSame(0, $beforeStart['processed']);
        $this->assertSame(0, BillingRevenueRecognitionEntry::query()->count());

        $afterEnd = $service->process(10, CarbonImmutable::parse('2026-02-01', 'Asia/Aden'));
        $this->assertSame(1, $afterEnd['processed']);
        $this->assertSame(1001, $afterEnd['amount_by_currency'][$this->currency->currency_id]);

        $this->schedule->refresh();
        $this->assertSame(1001, $this->schedule->recognized_minor);
        $this->assertSame('COMPLETED', $this->schedule->status);
        $this->assertSame('2026-01-10', $this->schedule->last_recognized_on->toDateString());
    }
}
