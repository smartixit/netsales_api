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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BillingCollectionsReportTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    private Currency $currency;

    private BillingAccount $account;

    private Network $network;

    private int $paymentMethodId;

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
            'network_name' => 'Collections Report Test '.uniqid(),
            'network_phone_number' => '73'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->account = BillingAccount::query()->create([
            'network_id' => $this->network->network_id,
            'account_number' => 'BA-'.Str::upper(Str::random(16)),
            'legal_name' => 'Collections Report Test',
            'default_currency_id' => $this->currency->currency_id,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_by' => $this->actor->user_id,
        ]);

        $this->paymentMethodId = (int) DB::table('billing_payment_methods')->insertGetId([
            'method_key' => 'TEST-'.Str::upper(Str::random(12)),
            'name' => 'Test transfer',
            'method_type' => 'MANUAL',
            'requires_proof' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cursor_pagination_handles_pending_payments_without_received_at(): void
    {
        $invoice = BillingInvoice::query()->create([
            'invoice_number' => 'COL-'.Str::upper(Str::random(12)),
            'billing_account_id' => $this->account->billing_account_id,
            'currency_id' => $this->currency->currency_id,
            'invoice_type' => 'STANDARD',
            'status' => BillingInvoiceStatus::OPEN,
            'period_start' => now('Asia/Aden')->toDateString(),
            'period_end' => now('Asia/Aden')->toDateString(),
            'issued_on' => now('Asia/Aden')->toDateString(),
            'due_on' => now('Asia/Aden')->addDays(7)->toDateString(),
            'subtotal_minor' => 1000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 1000,
            'paid_minor' => 0,
            'credited_minor' => 0,
            'remaining_minor' => 1000,
            'customer_snapshot' => ['billing_account_id' => $this->account->billing_account_id],
            'pricing_snapshot' => ['currency_code' => $this->currency->code],
            'created_by' => $this->actor->user_id,
        ]);

        DB::table('billing_payments')->insert([
            'payment_reference' => 'PAY-'.Str::upper(Str::random(12)),
            'idempotency_key' => 'collections-report-'.Str::uuid(),
            'invoice_id' => $invoice->invoice_id,
            'billing_account_id' => $this->account->billing_account_id,
            'currency_id' => $this->currency->currency_id,
            'payment_method_id' => $this->paymentMethodId,
            'amount_minor' => 1000,
            'status' => 'PENDING_REVIEW',
            'received_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $provider = app(BillingReportProviderRegistry::class)->get('collections');
        $page = $provider->build((int) $this->network->network_id, ['per_page' => 10]);

        $this->assertCount(1, $page->items());
        $this->assertNull($page->items()[0]->received_at);
    }
}
