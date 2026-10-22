<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\Network;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('role_key', RoleKey::SUPER_ADMIN->value)->first();
        $this->adminUser = User::where('role_id', $adminRole?->role_id)->first() ?? User::firstOrFail();

        $adminAuth = $this->createAuthenticatedSession($this->adminUser);
        $this->adminHeaders = $adminAuth['headers'];
    }

    /**
     * التحقق من رفض الطلبات غير المصرحة دون جلسة نشطة.
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(401);
    }

    /**
     * فحص لوحة القيادة السيادية للمدير العام.
     */
    public function test_super_admin_can_retrieve_dashboard_overview(): void
    {
        $response = $this->getJson('/api/v1/dashboard/overview', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'today');

        $data = $response->json('data');
        $this->assertArrayHasKey('kpis', $data);
        $this->assertArrayHasKey('alerts', $data);
        $this->assertArrayHasKey('recent_activities', $data);
        $this->assertArrayHasKey('telemetry', $data);
        $this->assertArrayHasKey('server_time', $data['telemetry']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $data['telemetry']['server_time']);
    }

    /**
     * فحص لوحة قيادة مزود الشبكة وحساب السيولة والمؤشرات.
     */
    public function test_network_owner_can_retrieve_dashboard_overview(): void
    {
        $suffix = uniqid();
        $ownerRole = Role::where('role_key', RoleKey::NETWORK_OWNER->value)->firstOrFail();

        $owner = User::create([
            'role_id' => $ownerRole->role_id,
            'user_full_name' => 'Network Owner '.$suffix,
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $network = Network::create([
            'owner_id' => $owner->user_id,
            'network_name' => 'Net Test '.$suffix,
            'network_phone_number' => '773111222',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        NetworkWallet::create([
            'network_id' => $network->network_id,
            'wallet_balance' => 50000.00,
            'status' => true,
        ]);

        $ownerAuth = $this->createAuthenticatedSession($owner);

        $response = $this->getJson('/api/v1/dashboard/overview', $ownerAuth['headers']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role_key', RoleKey::NETWORK_OWNER->value)
            ->assertJsonPath('data.context.network_id', $network->network_id)
            ->assertJsonPath('data.network.network_id', $network->network_id);

        $this->assertEquals(50000.0, (float) $response->json('data.liquidity.balance'));
        $this->assertEquals('YER', $response->json('data.liquidity.currency'));
    }

    /**
     * فحص لوحة قيادة وكيل نقطة البيع ومؤشرات المحفظة والمديونية.
     */
    public function test_pos_agent_can_retrieve_dashboard_overview(): void
    {
        $suffix = uniqid();
        $posRole = Role::where('role_key', RoleKey::POS_AGENT->value)->firstOrFail();

        $posAgent = User::create([
            'role_id' => $posRole->role_id,
            'user_full_name' => 'POS Agent '.$suffix,
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $pos = Pos::create([
            'owner_id' => $posAgent->user_id,
            'pos_name' => 'POS Branch '.$suffix,
            'pos_phone_number' => $posAgent->user_phone_number,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        $network = Network::first() ?? Network::create([
            'owner_id' => $this->adminUser->user_id,
            'network_name' => 'Net Parent '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        PosWallet::create([
            'network_id' => $network->network_id,
            'pos_id' => $pos->pos_id,
            'wallet_balance' => -1500.00,
            'status' => true,
        ]);

        $posAuth = $this->createAuthenticatedSession($posAgent);

        $response = $this->getJson('/api/v1/dashboard/overview', $posAuth['headers']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role_key', RoleKey::POS_AGENT->value)
            ->assertJsonPath('data.context.pos_id', $pos->pos_id)
            ->assertJsonPath('data.pos.pos_id', $pos->pos_id);

        $this->assertTrue((bool) $response->json('data.liquidity.is_overdrawn'));
        $this->assertEquals(1500.0, (float) $response->json('data.liquidity.debit_amount'));
    }

    /**
     * فحص تصفية الفترات الزمنية المختلفة.
     */
    public function test_dashboard_overview_respects_period_filtering(): void
    {
        $periods = ['today', 'yesterday', 'this_week', 'this_month', 'this_year'];

        foreach ($periods as $period) {
            $response = $this->getJson("/api/v1/dashboard/overview?period={$period}", $this->adminHeaders);

            $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.period', $period);
        }
    }
}
