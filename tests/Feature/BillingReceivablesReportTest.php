<?php

namespace Tests\Feature;

use App\Enums\BillingInvoiceStatus;
use App\Enums\NetworkStatus;
use App\Models\BillingAccount;
use App\Models\BillingInvoice;
use App\Models\Currency;
use App\Models\Network;
use App\Models\User;
use App\Services\Billing\Reports\BillingReportProviderRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BillingReceivablesReportTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    private Currency $currency;

    private BillingAccount $account;

    private Network $network;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', 'SUPER_ADMIN'))
            ->firstOrFail();
        $this->currency = Currency::query()->where('code', 'YER')->firstOrFail();

        $this->network = Network::query()->create([
            'owner_id' => $this->actor->user_id,
            'network_uuid' => (string) Str::uuid(),
            'network_name' => 'Receivables Report Test '.uniqid(),
            'network_phone_number' => '73'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->account = BillingAccount::query()->create([
            'network_id' => $this->network->network_id,
            'account_number' => 'BA-'.Str::upper(Str::random(16)),
            'legal_name' => 'Receivables Report Test',
            'default_currency_id' => $this->currency->currency_id,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_by' => $this->actor->user_id,
        ]);
    }

    public function test_aging_summary_includes_undated_due_amounts_without_losing_them_from_total(): void
    {
        $today = CarbonImmutable::now('Asia/Aden')->startOfDay();

        $this->createInvoice('UNDATED', BillingInvoiceStatus::OPEN, $today->subDays(30), null, 1000);
        $this->createInvoice('CURRENT', BillingInvoiceStatus::PARTIALLY_PAID, $today->subDays(5), $today->addDays(10), 2000);
        $this->createInvoice('OVERDUE', BillingInvoiceStatus::PARTIALLY_CREDITED, $today->subDays(40), $today->subDays(10), 3000);

        $provider = app(BillingReportProviderRegistry::class)->get('receivables');
        $summary = $provider->summary((int) $this->network->network_id, []);

        $this->assertSame(3, $summary['record_count']);
        $this->assertCount(1, $summary['aging_by_currency']);

        $aging = $summary['aging_by_currency'][0];
        $this->assertSame(1000, $aging['undated_minor']);
        $this->assertSame(2000, $aging['current_minor']);
        $this->assertSame(3000, $aging['days_1_30_minor']);
        $this->assertSame(
            6000,
            $aging['undated_minor']
                + $aging['current_minor']
                + $aging['days_1_30_minor']
                + $aging['days_31_60_minor']
                + $aging['days_61_90_minor']
                + $aging['over_90_minor']
        );
    }

    public function test_receivable_summary_and_cursor_details_apply_the_same_due_date_filter(): void
    {
        $today = CarbonImmutable::now('Asia/Aden')->startOfDay();

        $included = $this->createInvoice(
            'DUE-IN-RANGE',
            BillingInvoiceStatus::OPEN,
            $today->subDays(90),
            $today->subDays(5),
            1000
        );
        $this->createInvoice(
            'ISSUED-IN-RANGE-DUE-OUTSIDE',
            BillingInvoiceStatus::OPEN,
            $today,
            $today->addDays(20),
            2000
        );

        $provider = app(BillingReportProviderRegistry::class)->get('receivables');
        $filters = [
            'from_date' => $today->subDays(10)->toDateString(),
            'to_date' => $today->toDateString(),
            'per_page' => 100,
        ];

        $summary = $provider->summary((int) $this->network->network_id, $filters);
        $page = $provider->build((int) $this->network->network_id, $filters);

        $this->assertSame(1, $summary['record_count']);
        $this->assertCount(1, $page->items());
        $this->assertSame($included->invoice_id, $page->items()[0]->invoice_id);
    }

    public function test_receivables_cursor_order_uses_non_nullable_issue_date_and_keeps_undated_due_dates(): void
    {
        $today = CarbonImmutable::now('Asia/Aden')->startOfDay();
        $undated = $this->createInvoice(
            'UNDATED-CURSOR',
            BillingInvoiceStatus::OPEN,
            $today->subDays(20),
            null,
            1500
        );

        $provider = app(BillingReportProviderRegistry::class)->get('receivables');
        $page = $provider->build((int) $this->network->network_id, ['per_page' => 100]);

        $this->assertCount(1, $page->items());
        $this->assertSame($undated->invoice_id, $page->items()[0]->invoice_id);
        $this->assertNull($page->items()[0]->due_on);
        $this->assertNotNull($page->items()[0]->issued_on);
    }

    private function createInvoice(
        string $suffix,
        BillingInvoiceStatus $status,
        CarbonImmutable $issuedOn,
        ?CarbonImmutable $dueOn,
        int $remainingMinor
    ): BillingInvoice {
        return BillingInvoice::query()->create([
            'invoice_number' => 'RPT-'.$suffix.'-'.Str::upper(Str::random(8)),
            'billing_account_id' => $this->account->billing_account_id,
            'currency_id' => $this->currency->currency_id,
            'invoice_type' => 'STANDARD',
            'status' => $status,
            'issued_on' => $issuedOn->toDateString(),
            'due_on' => $dueOn?->toDateString(),
            'period_start' => $issuedOn->toDateString(),
            'period_end' => $issuedOn->addDays(30)->toDateString(),
            'subtotal_minor' => $remainingMinor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $remainingMinor,
            'paid_minor' => 0,
            'credited_minor' => 0,
            'remaining_minor' => $remainingMinor,
            'customer_snapshot' => ['billing_account_id' => $this->account->billing_account_id],
            'pricing_snapshot' => ['currency_code' => $this->currency->code],
            'created_by' => $this->actor->user_id,
        ]);
    }
}
