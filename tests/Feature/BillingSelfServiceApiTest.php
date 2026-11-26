<?php

namespace Tests\Feature;

use App\Enums\BillingInvoiceStatus;
use App\Enums\NetworkStatus;
use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Models\BillingAccount;
use App\Models\BillingInvoice;
use App\Models\BillingInvoiceLine;
use App\Models\BillingPayment;
use App\Models\BillingPaymentMethod;
use App\Models\Currency;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingSelfServiceApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $networkOwner;

    private User $otherNetworkOwner;

    private Network $network;

    private Network $otherNetwork;

    private BillingAccount $account;

    private BillingInvoice $invoice;

    /** @var array<string, string> */
    private array $ownerHeaders;

    /** @var array<string, string> */
    private array $otherOwnerHeaders;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $networkOwnerRole = Role::query()
            ->where('role_key', RoleKey::NETWORK_OWNER->value)
            ->firstOrFail();

        $this->networkOwner = $this->createNetworkOwner('Billing API Owner');
        $this->otherNetworkOwner = $this->createNetworkOwner('Billing API Other Owner');

        $this->network = $this->createNetworkFor($this->networkOwner, 'Billing API Network');
        $this->otherNetwork = $this->createNetworkFor($this->otherNetworkOwner, 'Billing API Other Network');

        NetworkUser::query()->create([
            'network_id' => $this->network->network_id,
            'user_id' => $this->networkOwner->user_id,
            'role_id' => $networkOwnerRole->role_id,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::query()->create([
            'network_id' => $this->otherNetwork->network_id,
            'user_id' => $this->otherNetworkOwner->user_id,
            'role_id' => $networkOwnerRole->role_id,
            'status' => 'ACTIVE',
        ]);

        $this->ownerHeaders = $this->createAuthenticatedSession($this->networkOwner)['headers'];
        $this->otherOwnerHeaders = $this->createAuthenticatedSession($this->otherNetworkOwner)['headers'];

        $this->currency = Currency::query()->where('code', 'YER')->firstOrFail();

        $this->account = BillingAccount::query()->create([
            'network_id' => $this->network->network_id,
            'account_number' => 'BA-'.Str::upper(Str::random(16)),
            'legal_name' => 'Billing API Contract Account',
            'default_currency_id' => $this->currency->currency_id,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_by' => $this->networkOwner->user_id,
        ]);

        $this->invoice = BillingInvoice::query()->create([
            'invoice_number' => 'INV-'.Str::upper(Str::random(16)),
            'billing_account_id' => $this->account->billing_account_id,
            'currency_id' => $this->currency->currency_id,
            'invoice_type' => 'STANDARD',
            'status' => BillingInvoiceStatus::OPEN,
            'period_start' => now('Asia/Aden')->startOfMonth()->toDateString(),
            'period_end' => now('Asia/Aden')->endOfMonth()->toDateString(),
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
            'created_by' => $this->networkOwner->user_id,
            'finalized_at' => now('Asia/Aden'),
        ]);

        BillingInvoiceLine::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'currency_id' => $this->currency->currency_id,
            'line_type' => 'SUBSCRIPTION',
            'description' => 'Billing API contract line',
            'quantity' => 1,
            'unit_amount_minor' => 1000,
            'subtotal_minor' => 1000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 1000,
            'source_snapshot' => ['billing_interval' => 'MONTH', 'interval_count' => 1],
        ]);
    }

    public function test_network_owner_can_submit_payment_and_read_server_calculated_invoice_balances(): void
    {
        $method = BillingPaymentMethod::query()
            ->where('method_key', 'CASH')
            ->firstOrFail();

        $paymentResponse = $this->withHeaders($this->ownerHeaders)
            ->postJson('/api/v1/billing/invoices/'.$this->invoice->invoice_id.'/payments', [
                'payment_method_id' => $method->payment_method_id,
                'amount_minor' => 600,
                'idempotency_key' => 'billing-api-'.Str::uuid(),
                'external_reference' => 'API-CONTRACT-600',
            ]);

        $paymentResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount_minor', 600)
            ->assertJsonPath('data.status', 'PENDING_REVIEW');

        $this->assertSame(1, BillingPayment::query()
            ->where('invoice_id', $this->invoice->invoice_id)
            ->count());

        $invoiceResponse = $this->withHeaders($this->ownerHeaders)
            ->getJson('/api/v1/billing/invoices/'.$this->invoice->invoice_id);

        $invoiceResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pending_payment_minor', 600)
            ->assertJsonPath('data.available_to_submit_minor', 400)
            ->assertJsonPath('data.can_submit_payment', true)
            ->assertJsonPath('data.paid_minor', 0)
            ->assertJsonPath('data.remaining_minor', 1000)
            ->assertJsonPath('data.lines.0.total_minor', 1000);

        $listResponse = $this->withHeaders($this->ownerHeaders)
            ->getJson('/api/v1/billing/accounts/'.$this->account->billing_account_id.'/invoices?per_page=10');

        $listResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.pending_payment_minor', 600)
            ->assertJsonPath('data.data.0.available_to_submit_minor', 400)
            ->assertJsonPath('data.data.0.can_submit_payment', true);

        $overLimitResponse = $this->withHeaders($this->ownerHeaders)
            ->postJson('/api/v1/billing/invoices/'.$this->invoice->invoice_id.'/payments', [
                'payment_method_id' => $method->payment_method_id,
                'amount_minor' => 401,
                'idempotency_key' => 'billing-api-over-limit-'.Str::uuid(),
            ]);

        $overLimitResponse
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(1, BillingPayment::query()
            ->where('invoice_id', $this->invoice->invoice_id)
            ->count());

        $this->invoice->refresh();
        $this->assertSame(0, $this->invoice->paid_minor);
        $this->assertSame(1000, $this->invoice->remaining_minor);
    }

    public function test_billing_invoice_routes_reject_cross_network_reads_and_payment_submission(): void
    {
        $invoiceList = $this->withHeaders($this->otherOwnerHeaders)
            ->getJson('/api/v1/billing/accounts/'.$this->account->billing_account_id.'/invoices');

        $invoiceList->assertForbidden();

        $invoiceDetails = $this->withHeaders($this->otherOwnerHeaders)
            ->getJson('/api/v1/billing/invoices/'.$this->invoice->invoice_id);

        $invoiceDetails->assertForbidden();

        $method = BillingPaymentMethod::query()
            ->where('method_key', 'CASH')
            ->firstOrFail();

        $paymentResponse = $this->withHeaders($this->otherOwnerHeaders)
            ->postJson('/api/v1/billing/invoices/'.$this->invoice->invoice_id.'/payments', [
                'payment_method_id' => $method->payment_method_id,
                'amount_minor' => 100,
                'idempotency_key' => 'billing-api-cross-network-'.Str::uuid(),
            ]);

        $paymentResponse->assertForbidden();

        $this->assertSame(0, BillingPayment::query()
            ->where('invoice_id', $this->invoice->invoice_id)
            ->count());

        $this->invoice->refresh();
        $this->assertSame(0, $this->invoice->paid_minor);
        $this->assertSame(1000, $this->invoice->remaining_minor);
    }

    private function createNetworkOwner(string $name): User
    {
        return $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => $name.' '.Str::random(6),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_email' => Str::uuid().'@netsales.local',
            'user_password' => bcrypt('Secret@123'),
            'status' => UserStatus::ACTIVE,
        ]);
    }

    private function createNetworkFor(User $owner, string $name): Network
    {
        return Network::query()->create([
            'owner_id' => $owner->user_id,
            'network_uuid' => (string) Str::uuid(),
            'network_name' => $name.' '.Str::random(6),
            'network_phone_number' => $owner->user_phone_number,
            'network_email' => $owner->user_email,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);
    }
}
