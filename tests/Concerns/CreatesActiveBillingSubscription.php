<?php

namespace Tests\Concerns;

use App\Enums\BillingFeatureType;
use App\Enums\BillingInterval;
use App\Enums\BillingPlanVersionStatus;
use App\Enums\BillingSubscriptionStatus;
use App\Models\BillingAccount;
use App\Models\BillingPlan;
use App\Models\BillingPlanFeature;
use App\Models\BillingPlanVersion;
use App\Models\BillingPrice;
use App\Models\BillingProduct;
use App\Models\BillingSubscription;
use App\Models\Currency;
use App\Models\Network;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

trait CreatesActiveBillingSubscription
{
    /**
     * Build an active subscription through the new multi-currency Billing schema.
     *
     * This fixture bypasses payment workflow only because the calling tests focus
     * on downstream entitlement guards, not subscription purchase or settlement.
     *
     * @param array<string, int> $resourceLimits
     */
    protected function createActiveBillingSubscriptionForNetwork(
        Network $network,
        array $resourceLimits = [],
        ?CarbonImmutable $currentPeriodEnd = null,
        ?User $actor = null
    ): BillingSubscription {
        $actor ??= User::query()->findOrFail($network->owner_id);
        $currency = Currency::query()->where('code', 'YER')->firstOrFail();
        $suffix = Str::lower(Str::random(14));

        $account = BillingAccount::query()->firstOrCreate(
            [
                'network_id' => $network->network_id,
                'default_currency_id' => $currency->currency_id,
            ],
            [
                'account_number' => 'BA-TEST-'.$suffix,
                'legal_name' => 'Billing Test Account',
                'payment_terms_days' => 0,
                'status' => 'ACTIVE',
                'created_by' => $actor->user_id,
            ]
        );

        $product = BillingProduct::query()->create([
            'product_key' => 'test-product-'.$suffix,
            'name' => 'Test Billing Product',
            'is_active' => true,
            'created_by' => $actor->user_id,
        ]);

        $plan = BillingPlan::query()->create([
            'product_id' => $product->product_id,
            'plan_key' => 'test-plan-'.$suffix,
            'name' => 'Entitlement Test Plan',
            'is_public' => false,
            'is_active' => true,
            'created_by' => $actor->user_id,
        ]);

        $version = BillingPlanVersion::query()->create([
            'plan_id' => $plan->plan_id,
            'version_number' => 1,
            'status' => BillingPlanVersionStatus::DRAFT,
            'created_by' => $actor->user_id,
        ]);

        $limits = array_replace([
            'max_pos_count' => 20,
            'max_voucher_packages' => 100,
            'max_network_members' => 30,
        ], $resourceLimits);

        foreach ($limits as $featureKey => $limit) {
            BillingPlanFeature::query()->create([
                'plan_version_id' => $version->plan_version_id,
                'feature_key' => $featureKey,
                'name' => $featureKey,
                'feature_type' => BillingFeatureType::RESOURCE_LIMIT,
                'value' => (string) $limit,
                'unit' => 'unit',
                'aggregation' => null,
                'reset_period' => null,
                'overage_behavior' => 'BLOCK',
            ]);
        }

        $price = BillingPrice::query()->create([
            'plan_version_id' => $version->plan_version_id,
            'currency_id' => $currency->currency_id,
            'billing_interval' => BillingInterval::MONTH,
            'interval_count' => 1,
            'unit_amount_minor' => 1000,
            'setup_fee_minor' => 0,
            'tax_behavior' => 'EXCLUSIVE',
            'is_active' => true,
        ]);

        $version->status = BillingPlanVersionStatus::PUBLISHED;
        $version->published_at = now('Asia/Aden');
        $version->save();

        $plan->current_version_id = $version->plan_version_id;
        $plan->save();

        $periodStart = CarbonImmutable::now('Asia/Aden');
        $periodEnd = $currentPeriodEnd ?? $periodStart->addDays(30);

        return BillingSubscription::query()->create([
            'billing_account_id' => $account->billing_account_id,
            'plan_id' => $plan->plan_id,
            'plan_version_id' => $version->plan_version_id,
            'price_id' => $price->price_id,
            'currency_id' => $currency->currency_id,
            'status' => BillingSubscriptionStatus::ACTIVE,
            'collection_method' => 'MANUAL',
            'quantity' => 1,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'terms_snapshot' => [
                'plan_name' => $plan->name,
                'plan_key' => $plan->plan_key,
                'currency_code' => $currency->code,
                'features' => $version->features()->get()->map(static fn (BillingPlanFeature $feature): array => [
                    'feature_key' => $feature->feature_key,
                    'type' => $feature->feature_type->value,
                    'value' => $feature->value,
                ])->all(),
            ],
            'created_by' => $actor->user_id,
        ]);
    }
}
