<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\ProviderSubscriptionStatus;
use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Models\ProviderSubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_receives_real_network_and_subscription_overview_data(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', RoleKey::SUPER_ADMIN->value))
            ->firstOrFail();

        $headers = $this->createAuthenticatedSession($admin)['headers'];
        $activeNetworksBefore = Network::query()
            ->where('status', NetworkStatus::ACTIVE->value)
            ->count();
        $expiringBefore = ProviderSubscription::query()
            ->where('status', ProviderSubscriptionStatus::ACTIVE->value)
            ->whereBetween('subscription_end_date', [
                now('Asia/Aden'),
                now('Asia/Aden')->copy()->addDays(7),
            ])
            ->count();

        $network = Network::create([
            'owner_id' => $admin->user_id,
            'network_name' => 'Overview Network '.Str::random(8),
            'network_phone_number' => '7'.random_int(100000000, 999999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $plan = SubscriptionPlan::create([
            'plan_name' => 'Overview Plan '.Str::uuid(),
            'plan_price' => '100.00',
            'plan_discount' => '0.00',
            'plan_validity' => 30,
            'status' => true,
        ]);

        $subscription = ProviderSubscription::create([
            'network_id' => $network->network_id,
            'plan_id' => $plan->plan_id,
            'subscription_start_date' => now('Asia/Aden')->subDays(20),
            'subscription_end_date' => now('Asia/Aden')->addDays(5),
            'status' => ProviderSubscriptionStatus::ACTIVE,
            'created_by' => $admin->user_id,
        ]);

        $response = $this->getJson('/api/v1/dashboard/overview', $headers);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.timezone', 'Asia/Aden')
            ->assertJsonPath('data.period.key', '30d')
            ->assertJsonPath('data.metrics.networks.active', $activeNetworksBefore + 1)
            ->assertJsonPath('data.metrics.subscriptions.expiring_within_7_days', $expiringBefore + 1)
            ->assertJsonFragment([
                'id' => (int) $subscription->subscription_id,
                'route_key' => 'provider_subscriptions',
            ]);

        $this->assertCount(30, $response->json('data.charts.subscription_trend'));
        $this->assertCount(30, $response->json('data.charts.operational_activity'));
        $this->assertArrayHasKey('generated_at', $response->json('data'));
    }

    public function test_chart_granularity_tracks_selected_dashboard_period(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', RoleKey::SUPER_ADMIN->value))
            ->firstOrFail();

        $headers = $this->createAuthenticatedSession($admin)['headers'];

        $weekly = $this->getJson('/api/v1/dashboard/overview?period=90d', $headers);
        $weekly->assertOk();
        $this->assertCount(13, $weekly->json('data.charts.subscription_trend'));
        $this->assertCount(13, $weekly->json('data.charts.operational_activity'));

        $monthly = $this->getJson('/api/v1/dashboard/overview?period=12m', $headers);
        $monthly->assertOk();
        $this->assertCount(12, $monthly->json('data.charts.subscription_trend'));
        $this->assertCount(12, $monthly->json('data.charts.operational_activity'));
    }

    public function test_support_agent_receives_only_metrics_allowed_by_its_role(): void
    {
        $role = Role::query()
            ->where('role_key', RoleKey::SUPPORT_AGENT->value)
            ->firstOrFail();

        $user = User::create([
            'role_id' => $role->role_id,
            'user_full_name' => 'Overview Support Agent',
            'user_phone_number' => '7'.random_int(100000000, 999999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $headers = $this->createAuthenticatedSession($user)['headers'];
        $response = $this->getJson('/api/v1/dashboard/overview', $headers);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'metrics' => ['networks', 'pos', 'support'],
                    'charts' => ['provider_distribution', 'operational_activity'],
                ],
            ]);

        $this->assertArrayNotHasKey('sales', $response->json('data.metrics'));
        $this->assertArrayNotHasKey('expenses', $response->json('data.metrics'));
        $this->assertArrayNotHasKey('subscriptions', $response->json('data.metrics'));
    }

    public function test_operational_roles_cannot_access_platform_overview(): void
    {
        $role = Role::query()
            ->where('role_key', RoleKey::NETWORK_OWNER->value)
            ->firstOrFail();

        $user = User::create([
            'role_id' => null,
            'user_full_name' => 'Overview Network Owner',
            'user_phone_number' => '7'.random_int(100000000, 999999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $network = Network::create([
            'owner_id' => $user->user_id,
            'network_name' => 'Overview Owner Network',
            'network_phone_number' => '7'.random_int(100000000, 999999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        NetworkUser::create([
            'network_id' => $network->network_id,
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
            'status' => 'ACTIVE',
        ]);

        $headers = $this->createAuthenticatedSession($user)['headers'];

        $this->getJson('/api/v1/dashboard/overview', $headers)
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_overview_rejects_an_unsupported_period(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', RoleKey::SUPER_ADMIN->value))
            ->firstOrFail();

        $headers = $this->createAuthenticatedSession($admin)['headers'];

        $this->getJson('/api/v1/dashboard/overview?period=all', $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }
}
