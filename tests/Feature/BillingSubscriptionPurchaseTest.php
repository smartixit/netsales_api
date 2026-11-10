<?php

namespace Tests\Feature;

use App\Enums\{BillingInterval, BillingInvoiceStatus, BillingSubscriptionStatus, NetworkStatus};
use App\Models\{
    BillingAccount,
    BillingInvoice,
    BillingPaymentMethod,
    BillingPlan,
    BillingPlanVersion,
    BillingPrice,
    BillingProduct,
    BillingRevenueRecognitionSchedule,
    Currency,
    Network,
    TreasuryAccount,
    User
};
use App\Services\Billing\{
    BillingCatalogService,
    BillingEntitlementService,
    BillingPaymentService,
    BillingReconciliationService,
    BillingSubscriptionService
};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingSubscriptionPurchaseTest extends TestCase
{
    use DatabaseTransactions;

    public function test_paid_purchase_creates_a_valid_period_and_keeps_entitlements_locked_until_payment(): void
    {
        $actor = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', 'SUPER_ADMIN'))
            ->firstOrFail();
        $currency = Currency::query()->where('code', 'YER')->firstOrFail();

        $network = Network::query()->create([
            'owner_id' => $actor->user_id,
            'network_uuid' => (string) Str::uuid(),
            'network_name' => 'Paid Billing Purchase '.uniqid(),
            'network_phone_number' => '73'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $account = BillingAccount::query()->create([
            'network_id' => $network->network_id,
            'account_number' => 'BA-'.Str::upper(Str::random(16)),
            'legal_name' => 'Paid Purchase Test',
            'default_currency_id' => $currency->currency_id,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_by' => $actor->user_id,
        ]);

        $product = BillingProduct::query()->create([
            'product_key' => 'billing-test-'.Str::lower(Str::random(8)),
            'name' => 'Billing Test Product',
            'is_active' => true,
            'created_by' => $actor->user_id,
        ]);

        $plan = BillingPlan::query()->create([
            'product_id' => $product->product_id,
            'plan_key' => 'monthly-'.Str::lower(Str::random(8)),
            'name' => 'Monthly Test Plan',
            'is_public' => true,
            'is_active' => true,
            'created_by' => $actor->user_id,
        ]);

        $version = BillingPlanVersion::query()->create([
            'plan_id' => $plan->plan_id,
            'version_number' => 1,
            'status' => 'DRAFT',
            'created_by' => $actor->user_id,
        ]);

        $price = BillingPrice::query()->create([
            'plan_version_id' => $version->plan_version_id,
            'currency_id' => $currency->currency_id,
            'billing_interval' => BillingInterval::MONTH,
            'interval_count' => 1,
            'unit_amount_minor' => 1000,
            'setup_fee_minor' => 200,
            'tax_behavior' => 'EXCLUSIVE',
            'is_active' => true,
        ]);

        app(BillingCatalogService::class)->publishVersion(
            (int) $version->plan_version_id,
            (int) $actor->user_id
        );

        $purchase = app(BillingSubscriptionService::class)->purchase(
            (int) $account->billing_account_id,
            (int) $price->price_id,
            $actor
        );

        $subscription = $purchase['subscription'];
        $invoice = $purchase['invoice'];

        $this->assertNotNull($invoice);
        $this->assertSame(BillingSubscriptionStatus::INCOMPLETE, $subscription->status);
        $this->assertNotNull($subscription->current_period_start);
        $this->assertNotNull($subscription->current_period_end);
        $this->assertSame($subscription->current_period_start->toDateString(), $invoice->period_start->toDateString());
        $this->assertSame($subscription->current_period_end->toDateString(), $invoice->period_end->endOfDay()->toDateString());
        $this->assertSame(BillingInvoiceStatus::OPEN, $invoice->status);
        $this->assertSame(1200, $invoice->total_minor);

        $schedules = BillingRevenueRecognitionSchedule::query()
            ->where('invoice_id', $invoice->invoice_id)
            ->orderBy('revenue_schedule_id')
            ->get();
        $this->assertCount(2, $schedules);
        $this->assertSame(['HOLD', 'HOLD'], $schedules->pluck('status')->all());
        $this->assertSame(1000, (int) $schedules->firstWhere('revenue_account_code', '4100')->total_minor);
        $this->assertSame(200, (int) $schedules->firstWhere('revenue_account_code', '4120')->total_minor);
        $this->assertSame(0, (int) $schedules->sum('recognized_minor'));

        $beforePaymentReconciliation = app(BillingReconciliationService::class)->report((int) $currency->currency_id);
        $beforePaymentCurrency = collect($beforePaymentReconciliation['currencies'])->first();
        $this->assertSame('MATCH', $beforePaymentCurrency['status'], json_encode($beforePaymentCurrency, JSON_THROW_ON_ERROR));

        $this->assertNull(app(BillingEntitlementService::class)->activeSubscriptionForAccount(
            (int) $account->billing_account_id
        ));

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $treasury = TreasuryAccount::query()->create([
            'account_code' => 'TR-PURCHASE-'.Str::upper(Str::random(8)),
            'name' => 'Purchase Test Cash Treasury',
            'account_type' => 'CASH',
            'currency_id' => $currency->currency_id,
            'status' => 'ACTIVE',
        ]);

        $payments = app(BillingPaymentService::class);
        $payment = $payments->recordManualPayment(
            (int) $invoice->invoice_id,
            (int) $method->payment_method_id,
            1200,
            'purchase-payment-'.Str::uuid(),
            $actor
        );
        $payment = $payments->verify(
            (int) $payment->payment_id,
            (int) $treasury->treasury_account_id,
            $actor
        );

        $this->assertSame('VERIFIED', $payment->status->value);
        $this->assertSame(2, BillingRevenueRecognitionSchedule::query()
            ->where('invoice_id', $invoice->invoice_id)
            ->where('status', 'PENDING')
            ->count());
        $subscription->refresh();
        $this->assertSame(BillingSubscriptionStatus::ACTIVE, $subscription->status);
        $active = app(BillingEntitlementService::class)->activeSubscriptionForAccount(
            (int) $account->billing_account_id
        );
        $this->assertNotNull($active);
        $this->assertSame($subscription->billing_subscription_id, $active->billing_subscription_id);

        $reconciliation = app(BillingReconciliationService::class)->report((int) $currency->currency_id);
        $currencyReport = collect($reconciliation['currencies'])->first();
        $this->assertNotNull($currencyReport);
        $this->assertSame('MATCH', $currencyReport['status'], json_encode($currencyReport, JSON_THROW_ON_ERROR));
    }
}
