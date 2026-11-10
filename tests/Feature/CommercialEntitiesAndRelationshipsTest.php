<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\Permission;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Concerns\CreatesActiveBillingSubscription;

class CommercialEntitiesAndRelationshipsTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesActiveBillingSubscription;

    protected User $superAdmin;

    protected User $networkOwnerA;

    protected User $networkOwnerB;

    protected User $posOwnerA;

    protected User $posOwnerB;

    protected Network $networkA;

    protected Network $networkB;

    protected Pos $posA;

    protected Pos $posB;

    protected Pos $unlinkedPos;

    protected NetworkPos $linkA;

    protected NetworkPos $linkB;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::where('role_key', RoleKey::SUPER_ADMIN->value)->first();
        $netRole = Role::where('role_key', RoleKey::NETWORK_OWNER->value)->first();
        $posRole = Role::where('role_key', RoleKey::POS_AGENT->value)->first();

        $posEditPerm = Permission::firstOrCreate(
            ['permission_key' => 'pos.edit'],
            ['permission_name' => 'تعديل نقطة بيع', 'module' => 'pos']
        );
        if ($posRole && ! $posRole->permissions()->where('permissions.permission_id', $posEditPerm->permission_id)->exists()) {
            $posRole->permissions()->attach($posEditPerm->permission_id);
        }

        $gov = Governorate::first() ?? Governorate::create(['governorate_name' => 'Sanaa']);
        $dir = Directorate::first() ?? Directorate::create(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Main']);

        // 1. Super Admin
        $this->superAdmin = User::whereHas('role', function ($q) {
            $q->where('role_key', RoleKey::SUPER_ADMIN->value);
        })->firstOrFail();

        // 2. Network Owners
        $this->networkOwnerA = User::create([
            'role_id' => null,
            'user_full_name' => 'Net Owner A',
            'user_phone_number' => '77110000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->networkOwnerB = User::create([
            'role_id' => null,
            'user_full_name' => 'Net Owner B',
            'user_phone_number' => '77220000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // 3. POS Owners
        $this->posOwnerA = User::create([
            'role_id' => null,
            'user_full_name' => 'POS Owner A',
            'user_phone_number' => '77330000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->posOwnerB = User::create([
            'role_id' => null,
            'user_full_name' => 'POS Owner B',
            'user_phone_number' => '77440000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $posOwnerC = User::create([
            'role_id' => null,
            'user_full_name' => 'POS Owner C',
            'user_phone_number' => '77550000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // 4. Networks
        $this->networkA = Network::create([
            'owner_id' => $this->networkOwnerA->user_id,
            'network_name' => 'Al-Amal Network A',
            'network_phone_number' => '771100000',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->networkB = Network::create([
            'owner_id' => $this->networkOwnerB->user_id,
            'network_name' => 'Al-Baraka Network B',
            'network_phone_number' => '772200000',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->createActiveBillingSubscriptionForNetwork($this->networkA, [], null, $this->networkOwnerA);
        $this->createActiveBillingSubscriptionForNetwork($this->networkB, [], null, $this->networkOwnerB);

        // 5. POS
        $this->posA = Pos::create([
            'owner_id' => $this->posOwnerA->user_id,
            'pos_name' => 'Al-Nour POS A',
            'pos_phone_number' => '773300000',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        $this->posB = Pos::create([
            'owner_id' => $this->posOwnerB->user_id,
            'pos_name' => 'Al-Fajr POS B',
            'pos_phone_number' => '774400000',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        $this->unlinkedPos = Pos::create([
            'owner_id' => $posOwnerC->user_id,
            'pos_name' => 'Unlinked Test POS',
            'pos_phone_number' => '775500000',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        // Memberships
        NetworkUser::create([
            'network_id' => $this->networkA->network_id,
            'user_id' => $this->networkOwnerA->user_id,
            'role_id' => $netRole?->role_id,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::create([
            'network_id' => $this->networkB->network_id,
            'user_id' => $this->networkOwnerB->user_id,
            'role_id' => $netRole?->role_id,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $this->posA->pos_id,
            'user_id' => $this->posOwnerA->user_id,
            'role_id' => $posRole?->role_id,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $this->posB->pos_id,
            'user_id' => $this->posOwnerB->user_id,
            'role_id' => $posRole?->role_id,
            'status' => 'ACTIVE',
        ]);

        // Links
        $this->linkA = NetworkPos::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'status' => true,
        ]);

        $this->linkB = NetworkPos::create([
            'network_id' => $this->networkB->network_id,
            'pos_id' => $this->posB->pos_id,
            'status' => true,
        ]);
    }

    // ==========================================
    // 1. NETWORK REGISTRY & ISOLATION TESTS
    // ==========================================

    public function test_network_owner_sees_only_own_networks_in_index(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson('/api/v1/networks');

        $response->assertStatus(200);
        $data = $response->json('data');
        $networkIds = collect($data)->pluck('network_id')->all();

        $this->assertContains($this->networkA->network_id, $networkIds);
        $this->assertNotContains($this->networkB->network_id, $networkIds);
    }

    public function test_network_owner_can_view_own_network_details(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson("/api/v1/networks/{$this->networkA->network_id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.network_id', $this->networkA->network_id);
    }

    public function test_network_owner_cannot_view_another_network_details_idor(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson("/api/v1/networks/{$this->networkB->network_id}");

        $response->assertStatus(403);
    }

    public function test_network_owner_can_update_own_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->putJson("/api/v1/networks/{$this->networkA->network_id}", [
            'network_name' => 'Updated Network A Name',
            'network_phone_number' => '771100099',
            'governorate_id' => $this->networkA->governorate_id,
            'directorate_id' => $this->networkA->directorate_id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('networks', [
            'network_id' => $this->networkA->network_id,
            'network_name' => 'Updated Network A Name',
        ]);
    }

    public function test_network_owner_cannot_update_another_network_idor(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->putJson("/api/v1/networks/{$this->networkB->network_id}", [
            'network_name' => 'Hacked Network B Name',
            'network_phone_number' => '772200099',
            'governorate_id' => $this->networkB->governorate_id,
            'directorate_id' => $this->networkB->directorate_id,
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_approve_and_reject_network(): void
    {
        $session = $this->createAuthenticatedSession($this->superAdmin);

        // Approve
        $approveResponse = $this->withHeaders($session['headers'])->postJson("/api/v1/networks/{$this->networkA->network_id}/approve", [
            'notes' => 'Approved by SuperAdmin in automated test',
        ]);
        $approveResponse->assertStatus(200);

        // Reject
        $rejectResponse = $this->withHeaders($session['headers'])->postJson("/api/v1/networks/{$this->networkA->network_id}/reject", [
            'rejection_reason' => 'Rejected for testing',
        ]);
        $rejectResponse->assertStatus(200);
    }

    public function test_network_owner_cannot_approve_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->postJson("/api/v1/networks/{$this->networkA->network_id}/approve");

        $response->assertStatus(403);
    }

    // ==========================================
    // 2. POS REGISTRY & ISOLATION TESTS
    // ==========================================

    public function test_pos_owner_sees_only_own_pos_in_index(): void
    {
        $session = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson('/api/v1/pos');

        $response->assertStatus(200);
        $data = $response->json('data');
        $posIds = collect($data)->pluck('pos_id')->all();

        $this->assertContains($this->posA->pos_id, $posIds);
        $this->assertNotContains($this->posB->pos_id, $posIds);
    }

    public function test_pos_owner_can_view_own_pos_details(): void
    {
        $session = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson("/api/v1/pos/{$this->posA->pos_id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.pos_id', $this->posA->pos_id);
    }

    public function test_pos_owner_cannot_view_another_pos_details_idor(): void
    {
        $session = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson("/api/v1/pos/{$this->posB->pos_id}");

        $response->assertStatus(403);
    }

    public function test_pos_owner_can_update_own_pos(): void
    {
        $session = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($session['headers'])->putJson("/api/v1/pos/{$this->posA->pos_id}", [
            'pos_name' => 'Updated POS A Name',
            'pos_phone_number' => '773300099',
            'governorate_id' => $this->posA->governorate_id,
            'directorate_id' => $this->posA->directorate_id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pos', [
            'pos_id' => $this->posA->pos_id,
            'pos_name' => 'Updated POS A Name',
        ]);
    }

    public function test_pos_owner_cannot_update_another_pos_idor(): void
    {
        $session = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($session['headers'])->putJson("/api/v1/pos/{$this->posB->pos_id}", [
            'pos_name' => 'Hacked POS B Name',
            'pos_phone_number' => '774400099',
            'governorate_id' => $this->posB->governorate_id,
            'directorate_id' => $this->posB->directorate_id,
        ]);

        $response->assertStatus(403);
    }

    // ==========================================
    // 3. NETWORK-POS RELATIONSHIP TESTS
    // ==========================================

    public function test_network_owner_can_list_pos_for_own_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson("/api/v1/networks/{$this->networkA->network_id}/pos");

        $response->assertStatus(200);
        $data = $response->json('data');
        $posIds = collect($data)->pluck('pos.pos_id')->all();

        $this->assertContains($this->posA->pos_id, $posIds);
    }

    public function test_network_owner_cannot_list_pos_for_another_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson("/api/v1/networks/{$this->networkB->network_id}/pos");

        $response->assertStatus(403);
    }

    public function test_network_owner_can_link_pos_to_own_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->postJson('/api/v1/network-pos', [
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->unlinkedPos->pos_id,
            'status' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('network_pos', [
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->unlinkedPos->pos_id,
            'status' => true,
        ]);
    }

    public function test_linking_already_linked_pos_returns_validation_error(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        // Attempt to link POS A which is already linked to Network A
        $response = $this->withHeaders($session['headers'])->postJson('/api/v1/network-pos', [
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'status' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_network_owner_cannot_link_pos_to_another_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->postJson('/api/v1/network-pos', [
            'network_id' => $this->networkB->network_id,
            'pos_id' => $this->unlinkedPos->pos_id,
            'status' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_network_owner_can_toggle_link_status_in_own_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->postJson("/api/v1/network-pos/{$this->linkA->network_pos_id}/toggle-status");

        $response->assertStatus(200);
        $this->assertDatabaseHas('network_pos', [
            'network_pos_id' => $this->linkA->network_pos_id,
            'status' => false,
        ]);
    }

    public function test_network_owner_cannot_toggle_link_status_in_another_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->postJson("/api/v1/network-pos/{$this->linkB->network_pos_id}/toggle-status");

        $response->assertStatus(403);
    }

    public function test_network_owner_can_unlink_pos_from_own_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->deleteJson("/api/v1/network-pos/{$this->linkA->network_pos_id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('network_pos', [
            'network_pos_id' => $this->linkA->network_pos_id,
        ]);
    }

    public function test_network_owner_cannot_unlink_pos_from_another_network(): void
    {
        $session = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($session['headers'])->deleteJson("/api/v1/network-pos/{$this->linkB->network_pos_id}");

        $response->assertStatus(403);
    }

    public function test_pos_owner_can_view_linked_networks(): void
    {
        $session = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson("/api/v1/pos/{$this->posA->pos_id}/networks");

        $response->assertStatus(200);
        $data = $response->json('data');
        $networkIds = collect($data)->pluck('network.network_id')->all();

        $this->assertContains($this->networkA->network_id, $networkIds);
    }

    public function test_pos_owner_cannot_view_linked_networks_for_another_pos(): void
    {
        $session = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($session['headers'])->getJson("/api/v1/pos/{$this->posB->pos_id}/networks");

        $response->assertStatus(403);
    }
}
