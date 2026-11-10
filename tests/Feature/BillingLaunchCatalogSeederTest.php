<?php

namespace Tests\Feature;

use App\Enums\BillingInterval;
use App\Models\BillingDunningPolicy;
use App\Models\BillingPlan;
use App\Models\BillingPlanVersion;
use App\Models\BillingPrice;
use App\Models\BillingTrialPolicy;
use Database\Seeders\BillingLaunchCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingLaunchCatalogSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_launch_catalog_contains_yer_prices_and_only_unpublished_drafts(): void
    {
        $expectedPrices = [
            'starter' => ['monthly' => 3_300_000, 'yearly' => 33_000_000],
            'growth' => ['monthly' => 6_600_000, 'yearly' => 66_000_000],
            'business' => ['monthly' => 12_400_000, 'yearly' => 124_000_000],
        ];

        foreach ($expectedPrices as $planKey => $expected) {
            $plan = BillingPlan::query()
                ->with(['currentVersion.prices.currency'])
                ->where('plan_key', $planKey)
                ->firstOrFail();

            $this->assertNull($plan->current_version_id, "{$planKey} must not be promoted by a seeder.");

            $version = BillingPlanVersion::query()
                ->where('plan_id', $plan->plan_id)
                ->where('version_number', 1)
                ->firstOrFail();

            $this->assertSame('DRAFT', $version->status->value);
            $this->assertNull($version->published_at);

            $prices = BillingPrice::query()
                ->where('plan_version_id', $version->plan_version_id)
                ->whereHas('currency', fn ($query) => $query->where('code', 'YER'))
                ->get()
                ->keyBy(fn (BillingPrice $price): string => $price->billing_interval->value);

            $this->assertSame($expected['monthly'], (int) $prices[BillingInterval::MONTH->value]->unit_amount_minor);
            $this->assertSame($expected['yearly'], (int) $prices[BillingInterval::YEAR->value]->unit_amount_minor);
            $this->assertSame(2, (int) $prices[BillingInterval::MONTH->value]->currency->decimal_places);
        }

        $trialPolicy = BillingTrialPolicy::query()
            ->where('policy_key', 'netsales-starter-14-day-trial')
            ->firstOrFail();
        $trialVersion = $trialPolicy->versions()->firstOrFail();

        $this->assertSame('DRAFT', $trialVersion->status);
        $this->assertTrue($trialVersion->is_enabled);
        $this->assertSame(14, $trialVersion->duration_value);
        $this->assertNull($trialPolicy->current_version_id);
        $this->assertNull($trialVersion->published_at);

        $dunningPolicy = BillingDunningPolicy::query()
            ->where('policy_key', 'platform-default')
            ->firstOrFail();
        $dunningVersion = $dunningPolicy->versions()->firstOrFail();

        $this->assertSame('DRAFT', $dunningVersion->status);
        $this->assertNull($dunningPolicy->current_version_id);
        $this->assertNull($dunningVersion->published_at);

        $this->assertSame(
            0,
            BillingPlanVersion::query()->where('status', 'PUBLISHED')->count(),
            'The launch seeder must not publish any plan version.'
        );
    }

    public function test_rerunning_the_seeder_preserves_platform_edits_to_draft_prices_and_limits(): void
    {
        $plan = BillingPlan::query()->where('plan_key', 'starter')->firstOrFail();
        $version = BillingPlanVersion::query()
            ->where('plan_id', $plan->plan_id)
            ->where('status', 'DRAFT')
            ->firstOrFail();

        $price = BillingPrice::query()
            ->where('plan_version_id', $version->plan_version_id)
            ->whereHas('currency', fn ($query) => $query->where('code', 'YER'))
            ->where('billing_interval', BillingInterval::MONTH)
            ->firstOrFail();

        $feature = $version->features()->where('feature_key', 'max_pos_count')->firstOrFail();

        $price->unit_amount_minor = 3_375_000;
        $price->save();
        $feature->value = '3';
        $feature->save();

        app(BillingLaunchCatalogSeeder::class)->run();

        $this->assertSame(
            3_375_000,
            (int) $price->refresh()->unit_amount_minor,
            'A seeder rerun must not overwrite the platform-approved draft price.'
        );
        $this->assertSame('3', $feature->refresh()->value);

    }
}
