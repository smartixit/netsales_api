<?php

namespace Tests\Feature;

use Database\Seeders\BillingLedgerChartSeeder;
use Database\Seeders\BillingMasterDataSeeder;
use Database\Seeders\BillingPaymentMethodSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingMasterDataSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_launch_catalog_publishes_approved_yer_prices_and_policy_versions(): void
    {
        $this->seed(CurrencySeeder::class);
        $this->seed(BillingMasterDataSeeder::class);
        $this->seed(BillingLedgerChartSeeder::class);
        $this->seed(BillingPaymentMethodSeeder::class);

        $currencies = DB::table('currencies')->get()->keyBy('code');
        $this->assertSame(['AED', 'SAR', 'USD', 'YER'], $currencies->keys()->sort()->values()->all());
        $this->assertTrue((bool) $currencies['YER']->is_active);

        foreach (['SAR', 'USD', 'AED'] as $code) {
            $this->assertFalse((bool) $currencies[$code]->is_active);
        }

        $product = DB::table('billing_products')
            ->where('product_key', 'netsales-b2b-network')
            ->first();
        $this->assertNotNull($product);
        $this->assertTrue((bool) $product->is_active);

        $plans = DB::table('billing_plans')->where('product_id', $product->product_id)->get();
        $this->assertCount(4, $plans);
        $this->assertSame(3, DB::table('billing_plan_versions')->where('status', 'PUBLISHED')->count());
        $this->assertSame(6, DB::table('billing_prices')->count());
        $this->assertSame(9, DB::table('billing_plan_features')->count());

        foreach ($plans as $plan) {
            if ($plan->plan_key === 'enterprise') {
                $this->assertFalse((bool) $plan->is_active);
                $this->assertNull($plan->current_version_id);
                continue;
            }

            $this->assertTrue((bool) $plan->is_active);
            $this->assertNotNull($plan->current_version_id);
        }

        $expectedPrices = [
            'starter' => ['MONTH' => 3_300_000, 'YEAR' => 33_000_000],
            'growth' => ['MONTH' => 6_600_000, 'YEAR' => 66_000_000],
            'business' => ['MONTH' => 12_400_000, 'YEAR' => 124_000_000],
        ];

        foreach ($expectedPrices as $planKey => $intervals) {
            $plan = $plans->firstWhere('plan_key', $planKey);
            $prices = DB::table('billing_prices')
                ->where('plan_version_id', $plan->current_version_id)
                ->join('currencies', 'billing_prices.currency_id', '=', 'currencies.currency_id')
                ->get()
                ->keyBy('billing_interval');

            $this->assertSame(['MONTH', 'YEAR'], $prices->keys()->sort()->values()->all());
            foreach ($intervals as $interval => $amountMinor) {
                $this->assertSame($amountMinor, (int) $prices[$interval]->unit_amount_minor);
                $this->assertSame('YER', $prices[$interval]->code);
                $this->assertSame(0, (int) $prices[$interval]->setup_fee_minor);
                $this->assertTrue((bool) $prices[$interval]->is_active);
            }
        }

        $this->assertSame(0, DB::table('billing_prices')
            ->join('currencies', 'billing_prices.currency_id', '=', 'currencies.currency_id')
            ->where('currencies.code', '<>', 'YER')
            ->count());

        $trial = DB::table('billing_trial_policies')
            ->join('billing_trial_policy_versions', 'billing_trial_policies.current_version_id', '=', 'billing_trial_policy_versions.trial_policy_version_id')
            ->where('billing_trial_policies.policy_key', 'netsales-starter-14-day-trial')
            ->first();
        $this->assertNotNull($trial);
        $this->assertSame('PUBLISHED', $trial->status);
        $this->assertSame(14, (int) $trial->duration_value);
        $this->assertSame('NETWORK', $trial->redemption_scope);
        $this->assertSame('NEW_BILLING_ACCOUNT', $trial->eligibility_mode);
        $this->assertSame('PURCHASE', $trial->start_trigger);
        $this->assertSame('CREATE_INVOICE', $trial->end_behavior);
        $this->assertSame('FULL_PLAN', $trial->entitlement_mode);

        $dunning = DB::table('billing_dunning_policies')
            ->join('billing_dunning_policy_versions', 'billing_dunning_policies.current_version_id', '=', 'billing_dunning_policy_versions.dunning_policy_version_id')
            ->where('billing_dunning_policies.policy_key', 'platform-default')
            ->first();
        $this->assertNotNull($dunning);
        $this->assertSame('PUBLISHED', $dunning->status);
        $this->assertSame(7, (int) $dunning->grace_period_days);
        $this->assertTrue((bool) $dunning->auto_suspend_after_grace);
        $this->assertSame([0, 2, 5, 7], json_decode($dunning->reminder_schedule_days, true));
        $this->assertSame([7, 3, 1], json_decode($dunning->pre_renewal_alert_days, true));

        $methods = DB::table('billing_payment_methods')->get()->keyBy('method_key');
        $this->assertTrue((bool) $methods['CASH']->is_active);
        $this->assertTrue((bool) $methods['BANK_TRANSFER']->is_active);
        $this->assertTrue((bool) $methods['E_WALLET']->is_active);
        $this->assertFalse((bool) $methods['PAYMENT_GATEWAY']->is_active);
        $this->assertSame(4, DB::table('billing_payment_method_currencies')->where('currency_id', $currencies['YER']->currency_id)->count());
        $this->assertSame(0, DB::table('billing_payment_method_currencies')->where('currency_id', '<>', $currencies['YER']->currency_id)->where('is_active', true)->count());
        $this->assertSame(8, DB::table('ledger_accounts')->where('currency_id', $currencies['YER']->currency_id)->count());
        $this->assertSame(8, DB::table('ledger_accounts')->where('is_control_account', true)->count());
        $this->assertSame(0, DB::table('ledger_accounts')->where('currency_id', '<>', $currencies['YER']->currency_id)->count());
        $this->assertSame(0, DB::table('treasury_accounts')->count());
    }

    public function test_master_data_seeders_are_idempotent_and_preserve_existing_policy_versions(): void
    {
        $this->seed(CurrencySeeder::class);
        $this->seed(BillingMasterDataSeeder::class);
        $this->seed(BillingLedgerChartSeeder::class);
        $this->seed(BillingPaymentMethodSeeder::class);

        $trialVersionId = DB::table('billing_trial_policies')
            ->where('policy_key', 'netsales-starter-14-day-trial')
            ->value('current_version_id');
        $dunningVersionId = DB::table('billing_dunning_policies')
            ->where('policy_key', 'platform-default')
            ->value('current_version_id');

        $this->seed(CurrencySeeder::class);
        $this->seed(BillingMasterDataSeeder::class);
        $this->seed(BillingLedgerChartSeeder::class);
        $this->seed(BillingPaymentMethodSeeder::class);

        $this->assertSame($trialVersionId, DB::table('billing_trial_policies')->where('policy_key', 'netsales-starter-14-day-trial')->value('current_version_id'));
        $this->assertSame($dunningVersionId, DB::table('billing_dunning_policies')->where('policy_key', 'platform-default')->value('current_version_id'));
        $this->assertSame(1, DB::table('billing_trial_policy_versions')->count());
        $this->assertSame(1, DB::table('billing_dunning_policy_versions')->count());
        $this->assertSame(3, DB::table('billing_plan_versions')->count());
        $this->assertSame(6, DB::table('billing_prices')->count());
        $this->assertSame(9, DB::table('billing_plan_features')->count());
    }
}
