<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SubscriptionRouteAuthorizationTest extends TestCase
{
    public function test_platform_subscription_read_routes_require_subscription_view_permission(): void
    {
        foreach ([
            'api.v1.subscription_plans.index',
            'api.v1.subscription_plans.show',
            'api.v1.features.index',
            'api.v1.subscription_plan_requests.index',
            'api.v1.subscription_plan_requests.show',
            'api.v1.subscription_plan_requests.receipt_link',
            'api.v1.provider_subscriptions.index',
            'api.v1.provider_subscriptions.show',
            'api.v1.provider_subscriptions.consumptions',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route [{$name}].");
            $this->assertContains('permission:subscriptions.view', $route->middleware(), "Route [{$name}] must require subscriptions.view.");
        }
    }

    public function test_plan_and_feature_mutations_require_least_privilege_permissions(): void
    {
        $permissions = [
            'api.v1.subscription_plans.store' => 'permission:subscriptions.create',
            'api.v1.subscription_plans.update' => 'permission:subscriptions.edit',
            'api.v1.subscription_plans.attach_feature' => 'permission:subscriptions.edit',
            'api.v1.subscription_plans.detach_feature' => 'permission:subscriptions.edit',
            'api.v1.subscription_plans.destroy' => 'permission:subscriptions.delete',
            'api.v1.subscription_plans.toggle_status' => 'permission:subscriptions.delete',
            'api.v1.features.store' => 'permission:subscriptions.edit',
            'api.v1.features.update' => 'permission:subscriptions.edit',
            'api.v1.features.destroy' => 'permission:subscriptions.edit',
        ];

        foreach ($permissions as $name => $required) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route [{$name}].");
            $this->assertContains($required, $route->middleware(), "Route [{$name}] must require {$required}.");
        }
    }

    public function test_request_review_and_subscription_extension_require_their_specific_permissions(): void
    {
        $permissions = [
            'api.v1.subscription_plan_requests.approve' => 'permission:subscriptions.approve',
            'api.v1.subscription_plan_requests.reject' => 'permission:subscriptions.reject',
            'api.v1.provider_subscriptions.extend' => 'permission:subscriptions.edit',
        ];

        foreach ($permissions as $name => $required) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route [{$name}].");
            $this->assertContains($required, $route->middleware(), "Route [{$name}] must require {$required}.");
        }
    }

    public function test_operational_request_submission_and_quota_consumption_keep_their_workspace_contract(): void
    {
        foreach ([
            'api.v1.subscription_plan_requests.store',
            'api.v1.provider_subscriptions.consume',
            'api.v1.provider_subscriptions.activate_early',
            'api.v1.provider_subscriptions.active_for_network',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route [{$name}].");
            $this->assertNotContains('permission:subscriptions.view', $route->middleware());
            $this->assertNotContains('permission:subscriptions.edit', $route->middleware());
        }
    }
}
