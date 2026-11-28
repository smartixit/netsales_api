<?php

namespace Tests\Feature;

use App\Enums\BillingInvoiceStatus;
use App\Enums\NetworkStatus;
use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Models\BillingAccount;
use App\Models\BillingDunningPolicyVersion;
use App\Models\BillingTrialPolicy;
use App\Models\BillingPlan;
use App\Models\BillingPlanVersion;
use App\Models\BillingPrice;
use App\Models\BillingInvoice;
use App\Models\BillingInvoiceLine;
use App\Models\BillingPayment;
use App\Models\BillingPaymentMethod;
use App\Models\Currency;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Services\Billing\BillingCatalogService;
use App\Services\Billing\BillingEntitlementService;
use App\Services\Billing\BillingPolicyManagementService;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesBillingDunningPolicyDraft;
use Tests\TestCase;

class BillingSelfServiceApiTest extends TestCase
{
    use CreatesBillingDunningPolicyDraft;
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

        $platformActor = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', RoleKey::SUPER_ADMIN->value))
            ->firstOrFail();
        $dunningDraft = $this->createBillingDunningPolicyDraft($platformActor);

        $starterPlan = BillingPlan::query()->where('plan_key', 'starter')->firstOrFail();
        $starterPlan->is_active = true;
        $starterPlan->save();

        $starterVersion = BillingPlanVersion::query()->create([
            'plan_id' => $starterPlan->plan_id,
            'version_number' => (int) BillingPlanVersion::query()->where('plan_id', $starterPlan->plan_id)->max('version_number') + 1,
            'status' => 'DRAFT',
            'dunning_policy_version_id' => $dunningDraft->dunning_policy_version_id,
            'created_by' => $platformActor->user_id,
        ]);

        BillingPrice::query()->create([
            'plan_version_id' => $starterVersion->plan_version_id,
            'currency_id' => $this->currency->currency_id,
            'billing_interval' => 'MONTH',
            'interval_count' => 1,
            'unit_amount_minor' => 1000,
            'setup_fee_minor' => 0,
            'tax_behavior' => 'EXCLUSIVE',
            'dunning_policy_version_id' => $dunningDraft->dunning_policy_version_id,
            'is_active' => true,
        ]);

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
            'status' => BillingInvoiceStatus::DRAFT,
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

        $this->invoice->status = BillingInvoiceStatus::OPEN;
        $this->invoice->finalized_at = now('Asia/Aden');
        $this->invoice->save();
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
            ->assertJsonPath('data.0.invoice_id', $this->invoice->invoice_id)
            ->assertJsonPath('data.0.pending_payment_minor', 600)
            ->assertJsonPath('data.0.available_to_submit_minor', 400)
            ->assertJsonPath('data.0.can_submit_payment', true);

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

    public function test_statement_is_scoped_to_owner_and_keeps_currency_balances_separate(): void
    {
        $response = $this->withHeaders($this->ownerHeaders)
            ->getJson('/api/v1/billing/accounts/'.$this->account->billing_account_id.'/statement?from='.now('Asia/Aden')->toDateString().'&to='.now('Asia/Aden')->toDateString());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.billing_account_id', $this->account->billing_account_id)
            ->assertJsonPath('data.currency_aggregation', 'SEPARATE_ONLY_NO_CROSS_CURRENCY_TOTAL')
            ->assertJsonPath('data.balances_by_currency.0.currency.currency_id', $this->currency->currency_id)
            ->assertJsonPath('data.balances_by_currency.0.invoiced_minor', 1000)
            ->assertJsonPath('data.balances_by_currency.0.closing_receivable_minor', 1000);

        $crossNetwork = $this->withHeaders($this->otherOwnerHeaders)
            ->getJson('/api/v1/billing/accounts/'.$this->account->billing_account_id.'/statement');

        $crossNetwork->assertForbidden();
    }

    public function test_network_owner_can_purchase_subscription_and_cancel_it_through_api(): void
    {
        $platformActor = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', RoleKey::SUPER_ADMIN->value))
            ->firstOrFail();

        $dunningVersion = BillingDunningPolicyVersion::query()
            ->where('status', 'DRAFT')
            ->whereHas('policy', fn ($query) => $query->where('policy_key', 'platform-default'))
            ->firstOrFail();

        app(BillingPolicyManagementService::class)->publishDunningVersion(
            (int) $dunningVersion->dunning_policy_version_id,
            $platformActor
        );

        $planVersion = BillingPlanVersion::query()
            ->where('status', 'DRAFT')
            ->whereHas('plan', fn ($query) => $query->where('plan_key', 'starter'))
            ->firstOrFail();

        // This scenario verifies a paid invoice. Disable only the default trial
        // fallback inside this transaction, and detach optional draft plan policy.
        $planVersion->trial_policy_version_id = null;
        $planVersion->save();
        BillingTrialPolicy::query()
            ->where('policy_key', 'platform-default')
            ->update(['is_active' => false]);

        $price = BillingPrice::query()
            ->where('plan_version_id', $planVersion->plan_version_id)
            ->where('currency_id', $this->currency->currency_id)
            ->where('billing_interval', 'MONTH')
            ->where('is_active', true)
            ->firstOrFail();
        $price->trial_policy_version_id = null;
        $price->save();

        app(BillingCatalogService::class)->publishVersion(
            (int) $planVersion->plan_version_id,
            (int) $platformActor->user_id
        );

        $purchase = $this->withHeaders($this->ownerHeaders)
            ->postJson('/api/v1/billing/accounts/'.$this->account->billing_account_id.'/subscriptions', [
                'price_id' => $price->price_id,
            ]);

        $purchase->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subscription.status', 'INCOMPLETE')
            ->assertJsonPath('data.invoice.status', 'OPEN');

        $subscriptionId = (int) $purchase->json('data.subscription.billing_subscription_id');
        $this->assertGreaterThan(0, $subscriptionId);

        $this->assertNull(app(BillingEntitlementService::class)->activeSubscriptionForAccount(
            (int) $this->account->billing_account_id
        ));

        $cancel = $this->withHeaders($this->ownerHeaders)
            ->postJson('/api/v1/billing/subscriptions/'.$subscriptionId.'/cancel', [
                'at_period_end' => false,
            ]);

        $cancel->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'CANCELED');

        $outbox = \DB::table('outbox_messages')
            ->where('aggregate_type', 'BILLING_SUBSCRIPTION')
            ->where('aggregate_id', $subscriptionId)
            ->where('event_type', 'billing.lifecycle')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($outbox);
        $payload = json_decode((string) $outbox->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((int) $this->networkOwner->user_id, $payload['user_id']);
        $this->assertSame('BILLING_SUBSCRIPTION_CANCELED', $payload['notification_type']);
        $this->assertStringStartsWith('↩️', $payload['title']);
        $this->assertStringStartsWith('↩️', $payload['message']);
        $this->assertTrue(\App\Enums\NotificationType::BILLING_SUBSCRIPTION_CANCELLATION_SCHEDULED->requiresEntityId());
    }

    public function test_network_owner_can_schedule_cancellation_after_verified_activation_through_api(): void
    {
        $platformActor = User::query()->whereHas('role', fn ($query) => $query->where('role_key', RoleKey::SUPER_ADMIN->value))->firstOrFail();
        $dunning = BillingDunningPolicyVersion::query()->where('status', 'DRAFT')->whereHas('policy', fn ($query) => $query->where('policy_key', 'platform-default'))->firstOrFail();
        app(BillingPolicyManagementService::class)->publishDunningVersion((int) $dunning->dunning_policy_version_id, $platformActor);
        $plan = BillingPlanVersion::query()->where('status', 'DRAFT')->whereHas('plan', fn ($query) => $query->where('plan_key', 'starter'))->firstOrFail();
        // This scenario also requires a paid subscription rather than a trial.
        $plan->trial_policy_version_id = null;
        $plan->save();
        BillingTrialPolicy::query()->where('policy_key', 'platform-default')->update(['is_active' => false]);
        $price = BillingPrice::query()->where('plan_version_id', $plan->plan_version_id)->where('currency_id', $this->currency->currency_id)->where('billing_interval', 'MONTH')->where('is_active', true)->firstOrFail();
        $price->trial_policy_version_id = null;
        $price->save();
        app(BillingCatalogService::class)->publishVersion((int) $plan->plan_version_id, (int) $platformActor->user_id);

        $purchase = $this->withHeaders($this->ownerHeaders)->postJson('/api/v1/billing/accounts/'.$this->account->billing_account_id.'/subscriptions', ['price_id' => $price->price_id]);
        $purchase->assertCreated()->assertJsonPath('data.subscription.status', 'INCOMPLETE')->assertJsonPath('data.invoice.status', 'OPEN');
        $subscriptionId = (int) $purchase->json('data.subscription.billing_subscription_id');
        $invoiceId = (int) $purchase->json('data.invoice.invoice_id');
        $total = (int) $purchase->json('data.invoice.total_minor');
        $this->assertGreaterThan(0, $total);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $payment = $this->withHeaders($this->ownerHeaders)->postJson('/api/v1/billing/invoices/'.$invoiceId.'/payments', [
            'payment_method_id' => $method->payment_method_id,
            'amount_minor' => $total,
            'idempotency_key' => 'billing-cancel-lifecycle-'.Str::uuid(),
        ]);
        $payment->assertCreated()->assertJsonPath('data.status', 'PENDING_REVIEW');

        $treasury = \App\Models\TreasuryAccount::query()->create([
            'account_code' => 'TR-CANCEL-'.Str::upper(Str::random(10)),
            'name' => 'Billing Cancellation Treasury',
            'account_type' => 'CASH',
            'currency_id' => $this->currency->currency_id,
            'status' => 'ACTIVE',
        ]);
        app(\App\Services\Billing\BillingLedgerChartService::class)->ensureTreasuryAccount($treasury);
        $platformHeaders = $this->createAuthenticatedSession($platformActor)['headers'];
        $this->withHeaders($platformHeaders)->postJson('/api/v1/platform/billing/payments/'.$payment->json('data.payment_id').'/verify', [
            'treasury_account_id' => $treasury->treasury_account_id,
        ])->assertOk()->assertJsonPath('data.status', 'VERIFIED');

        $this->assertNotNull(app(BillingEntitlementService::class)->activeSubscriptionForAccount((int) $this->account->billing_account_id));
        $cancel = $this->withHeaders($this->ownerHeaders)->postJson('/api/v1/billing/subscriptions/'.$subscriptionId.'/cancel', ['at_period_end' => true]);
        $cancel->assertOk()->assertJsonPath('data.status', 'ACTIVE')->assertJsonPath('data.cancel_at_period_end', true);
        $this->assertNotNull(app(BillingEntitlementService::class)->activeSubscriptionForAccount((int) $this->account->billing_account_id));

        $outbox = \DB::table('outbox_messages')->where('aggregate_type', 'BILLING_SUBSCRIPTION')->where('aggregate_id', $subscriptionId)->where('event_type', 'billing.lifecycle')->orderByDesc('id')->first();
        $this->assertNotNull($outbox);
        $payload = json_decode((string) $outbox->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('BILLING_SUBSCRIPTION_CANCELLATION_SCHEDULED', $payload['notification_type']);
        $this->assertSame((int) $this->networkOwner->user_id, $payload['user_id']);
        $this->assertStringStartsWith('📅', $payload['title']);
        $this->assertStringStartsWith('📅', $payload['message']);
    }

    private function createNetworkOwner(string $name): User
    {
        return $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => $name.' '.Str::random(6),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_email' => Str::lower(Str::random(12)).'@ns.test',
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
