<?php

namespace Tests\Unit;

use App\Enums\BillingFeatureType;
use App\Enums\BillingSubscriptionStatus;
use App\Models\BillingPlanFeature;
use App\Models\BillingPlanVersion;
use App\Models\BillingSubscription;
use App\Services\Billing\BillingEntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingEntitlementServiceTest extends TestCase
{
    private function subscription(array $trialPolicy, int $limit = 3): BillingSubscription
    {
        $feature = new BillingPlanFeature([
            'feature_key' => 'max_pos_count',
            'feature_type' => BillingFeatureType::RESOURCE_LIMIT,
            'value' => (string) $limit,
        ]);
        $feature->setAttribute('feature_type', BillingFeatureType::RESOURCE_LIMIT);

        $version = new BillingPlanVersion();
        $version->setRelation('features', new Collection([$feature]));

        $subscription = new BillingSubscription();
        $subscription->setAttribute('status', BillingSubscriptionStatus::TRIALING);
        $subscription->setAttribute('current_period_end', CarbonImmutable::now('Asia/Aden')->addDay());
        $subscription->setAttribute('trial_policy_snapshot', $trialPolicy);
        $subscription->setRelation('planVersion', $version);

        return $subscription;
    }

    public function test_none_trial_mode_blocks_resource_quota_even_when_plan_has_capacity(): void
    {
        $subscription = $this->subscription(['entitlement_mode' => 'NONE']);

        $this->expectException(ValidationException::class);
        app(BillingEntitlementService::class)->assertResourceLimit($subscription, 'max_pos_count', 0);
    }

    public function test_restricted_trial_mode_only_allows_explicitly_listed_resource_features(): void
    {
        $service = app(BillingEntitlementService::class);
        $subscription = $this->subscription([
            'entitlement_mode' => 'RESTRICTED',
            'entitlement_config' => ['allowed_features' => []],
        ]);

        $this->expectException(ValidationException::class);
        $service->assertResourceLimit($subscription, 'max_pos_count', 0);
    }

    public function test_restricted_trial_allows_listed_resource_feature_and_still_enforces_its_limit(): void
    {
        $service = app(BillingEntitlementService::class);
        $subscription = $this->subscription([
            'entitlement_mode' => 'RESTRICTED',
            'entitlement_config' => ['allowed_features' => ['max_pos_count']],
        ]);

        $service->assertResourceLimit($subscription, 'max_pos_count', 2);

        $this->expectException(ValidationException::class);
        $service->assertResourceLimit($subscription, 'max_pos_count', 3);
    }

    public function test_full_plan_trial_keeps_the_configured_resource_limit(): void
    {
        $service = app(BillingEntitlementService::class);
        $subscription = $this->subscription(['entitlement_mode' => 'FULL_PLAN']);

        $service->assertResourceLimit($subscription, 'max_pos_count', 2);

        $this->expectException(ValidationException::class);
        $service->assertResourceLimit($subscription, 'max_pos_count', 3);
    }

    public function test_unlimited_resource_limit_is_not_artificially_capped(): void
    {
        $subscription = $this->subscription(['entitlement_mode' => 'FULL_PLAN'], -1);

        app(BillingEntitlementService::class)->assertResourceLimit(
            $subscription,
            'max_pos_count',
            PHP_INT_MAX - 1
        );

        $this->assertTrue(true);
    }
}
