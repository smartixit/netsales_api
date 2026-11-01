<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkMembershipAuditLog;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MembershipAndRoleScopeTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $superAdminRole;

    protected Role $networkOwnerRole;

    protected Role $posAgentRole;

    protected Role $networkAccountantRole;

    protected Role $posAccountantRole;

    protected Network $network;

    protected Pos $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::where('role_key', RoleKey::SUPER_ADMIN->value)->firstOrFail();
        $this->networkOwnerRole = Role::where('role_key', RoleKey::NETWORK_OWNER->value)->firstOrFail();
        $this->posAgentRole = Role::where('role_key', RoleKey::POS_AGENT->value)->firstOrFail();
        $this->networkAccountantRole = Role::where('role_key', RoleKey::NETWORK_ACCOUNTANT->value)->firstOrFail();
        $this->posAccountantRole = Role::where('role_key', RoleKey::POS_ACCOUNTANT->value)->firstOrFail();

        $gov = Governorate::firstOrFail();
        $dir = Directorate::firstOrFail();

        $owner = User::create([
            'role_id' => null,
            'user_full_name' => 'Owner '.uniqid(),
            'user_phone_number' => '71'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->network = Network::create([
            'owner_id' => $owner->user_id,
            'network_name' => 'NetScope '.uniqid(),
            'network_phone_number' => '71'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => 'ACTIVE',
        ]);

        $this->pos = Pos::create([
            'owner_id' => $owner->user_id,
            'pos_name' => 'PosScope '.uniqid(),
            'pos_phone_number' => '71'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Test 1: Users table allows nullable role_id for tenant users.
     */
    public function test_user_can_have_null_role_id(): void
    {
        $user = User::create([
            'role_id' => null,
            'user_full_name' => 'Tenant User '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertNull($user->role_id);
        $user->refresh();
        $this->assertNull($user->role_id);
    }

    /**
     * Test 2: Database prevents assigning a non-PLATFORM role directly to users.role_id.
     */
    public function test_database_rejects_network_role_in_users_table(): void
    {
        $this->expectException(QueryException::class);

        User::create([
            'role_id' => $this->networkOwnerRole->role_id, // Scope is NETWORK, not PLATFORM
            'user_full_name' => 'Invalid Net User',
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test 3: Database allows PLATFORM role in users.role_id.
     */
    public function test_database_allows_platform_role_in_users_table(): void
    {
        $user = User::create([
            'role_id' => $this->superAdminRole->role_id, // Scope is PLATFORM
            'user_full_name' => 'Admin User '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertEquals($this->superAdminRole->role_id, $user->role_id);
    }

    /**
     * Test 4: network_users accepts NETWORK-scoped role and rejects PLATFORM or POS role.
     */
    public function test_network_users_enforces_network_scope(): void
    {
        $user = User::create([
            'role_id' => null,
            'user_full_name' => 'Network Staff '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // Success: Network-scoped role
        $member = NetworkUser::create([
            'network_id' => $this->network->network_id,
            'user_id' => $user->user_id,
            'role_id' => $this->networkAccountantRole->role_id,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('network_users', [
            'network_id' => $this->network->network_id,
            'user_id' => $user->user_id,
            'role_id' => $this->networkAccountantRole->role_id,
        ]);

        // Failure: Attempting to assign POS role to network_users
        $this->expectException(QueryException::class);

        NetworkUser::create([
            'network_id' => $this->network->network_id,
            'user_id' => $user->user_id,
            'role_id' => $this->posAgentRole->role_id, // POS scope!
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Test 5: pos_users accepts POS-scoped role and rejects NETWORK role.
     */
    public function test_pos_users_enforces_pos_scope(): void
    {
        $user = User::create([
            'role_id' => null,
            'user_full_name' => 'POS Staff '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // Success: POS-scoped role
        $posMember = PosUser::create([
            'pos_id' => $this->pos->pos_id,
            'user_id' => $user->user_id,
            'role_id' => $this->posAccountantRole->role_id,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('pos_users', [
            'pos_id' => $this->pos->pos_id,
            'user_id' => $user->user_id,
            'role_id' => $this->posAccountantRole->role_id,
        ]);

        // Failure: Attempting to assign NETWORK role to pos_users
        $this->expectException(QueryException::class);

        PosUser::create([
            'pos_id' => $this->pos->pos_id,
            'user_id' => $user->user_id,
            'role_id' => $this->networkOwnerRole->role_id, // NETWORK scope!
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Test 6: Audit log creation with composite identity foreign key.
     */
    public function test_membership_audit_log_tracks_event(): void
    {
        $admin = User::firstOrFail();

        $user = User::create([
            'role_id' => null,
            'user_full_name' => 'Audited User '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        NetworkUser::create([
            'network_id' => $this->network->network_id,
            'user_id' => $user->user_id,
            'role_id' => $this->networkOwnerRole->role_id,
            'status' => 'ACTIVE',
            'last_event_seq' => 1,
        ]);

        $audit = NetworkMembershipAuditLog::create([
            'network_id' => $this->network->network_id,
            'user_id' => $user->user_id,
            'event_seq' => 1,
            'action' => 'CREATED',
            'actor_id' => $admin->user_id,
            'old_state' => null,
            'new_state' => ['role_id' => $this->networkOwnerRole->role_id, 'status' => 'ACTIVE'],
        ]);

        $this->assertDatabaseHas('network_membership_audit_logs', [
            'audit_id' => $audit->audit_id,
            'network_id' => $this->network->network_id,
            'user_id' => $user->user_id,
            'event_seq' => 1,
        ]);
    }
}
