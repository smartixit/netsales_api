<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Models\Role;
use App\Models\SecurityBan;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlatformUserAdministrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_support_agent_can_read_users_but_cannot_mutate_users_or_manage_security(): void
    {
        $support = $this->makePlatformUser(RoleKey::SUPPORT_AGENT, 'Support Agent');
        $target = $this->makePlatformUser(RoleKey::ACCOUNTANT, 'Target User');
        $session = $this->createAuthenticatedSession($support);

        $this->getJson('/api/v1/users', $session['headers'])->assertOk();
        $this->getJson('/api/v1/users/'.$target->user_id, $session['headers'])
            ->assertOk()
            ->assertJsonMissingPath('data.devices')
            ->assertJsonMissingPath('data.security_bans');
        $this->postJson('/api/v1/users', [], $session['headers'])->assertForbidden();
        $this->postJson('/api/v1/security/ban-user', [], $session['headers'])->assertForbidden();
        $this->getJson('/api/v1/security/users/'.$target->user_id.'/sessions', $session['headers'])
            ->assertForbidden();
    }

    public function test_device_api_never_serializes_the_fcm_registration_token(): void
    {
        $admin = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Platform Admin');
        $target = $this->makePlatformUser(RoleKey::SUPPORT_AGENT, 'Target User');
        $device = UserDevice::query()->create([
            'user_id' => $target->user_id,
            'device_uuid' => 'test-device-'.bin2hex(random_bytes(8)),
            'device_name' => 'Test Android Device',
            'device_type' => 'ANDROID',
            'fcm_token' => 'private-fcm-registration-token',
            'is_primary' => true,
            'status' => 'ACTIVE',
        ]);
        $session = $this->createAuthenticatedSession($admin);

        $this->getJson('/api/v1/security/users/'.$target->user_id.'/devices', $session['headers'])
            ->assertOk()
            ->assertJsonPath('data.0.device_id', $device->device_id)
            ->assertJsonMissingPath('data.0.fcm_token')
            ->assertDontSee('private-fcm-registration-token');
    }

    public function test_device_ban_history_is_included_in_user_detail_and_can_be_lifted(): void
    {
        $admin = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Platform Admin');
        $target = $this->makePlatformUser(RoleKey::SUPPORT_AGENT, 'Target User');
        $device = UserDevice::query()->create([
            'user_id' => $target->user_id,
            'device_uuid' => 'test-device-'.bin2hex(random_bytes(8)),
            'device_name' => 'Test Windows Device',
            'device_type' => 'WINDOWS',
            'fcm_token' => null,
            'is_primary' => false,
            'status' => 'ACTIVE',
        ]);
        $session = $this->createAuthenticatedSession($admin);

        $this->postJson('/api/v1/security/ban-device', [
            'device_id' => $device->device_id,
            'ban_type' => 'TEMPORARY',
            'ban_reason' => 'Temporary security review',
            'end_at' => now('Asia/Aden')->addHours(3)->toDateTimeString(),
        ], $session['headers'])->assertOk();

        $detail = $this->getJson('/api/v1/users/'.$target->user_id, $session['headers'])
            ->assertOk()
            ->assertJsonPath('data.devices.0.device_id', $device->device_id)
            ->assertJsonPath('data.devices.0.security_bans.0.device_id', $device->device_id)
            ->assertJsonPath('data.devices.0.security_bans.0.ban_type', 'TEMPORARY')
            ->assertJsonPath('data.devices.0.status', 'BANNED');

        $banId = $detail->json('data.devices.0.security_bans.0.ban_id');

        $this->deleteJson('/api/v1/security/bans/'.$banId, [], $session['headers'])
            ->assertOk();

        $this->getJson('/api/v1/users/'.$target->user_id, $session['headers'])
            ->assertOk()
            ->assertJsonPath('data.devices.0.status', 'ACTIVE')
            ->assertJsonPath('data.devices.0.security_bans', []);
    }

    public function test_accountant_without_users_view_cannot_read_users(): void
    {
        $accountant = $this->makePlatformUser(RoleKey::ACCOUNTANT, 'Platform Accountant');
        $session = $this->createAuthenticatedSession($accountant);

        $this->getJson('/api/v1/users', $session['headers'])->assertForbidden();
    }

    public function test_roles_reference_returns_platform_roles_only(): void
    {
        $admin = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Platform Admin');
        $session = $this->createAuthenticatedSession($admin);

        $roles = $this->getJson('/api/v1/roles', $session['headers'])
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($roles);
        foreach ($roles as $role) {
            $this->assertSame(RoleScope::PLATFORM->value, $role['scope']);
        }
    }

    public function test_creating_a_user_rejects_a_non_platform_role(): void
    {
        $admin = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Platform Admin');
        $session = $this->createAuthenticatedSession($admin);
        $networkRole = Role::byKey(RoleKey::NETWORK_OWNER)->firstOrFail();

        $this->postJson('/api/v1/users', [
            'role_id' => $networkRole->role_id,
            'user_full_name' => 'Invalid Platform User',
            'user_phone_number' => '77'.random_int(1000000, 9999999),
            'user_password' => 'SecurePassword123',
        ], $session['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');
    }

    public function test_generic_user_update_cannot_bypass_security_ban_lifecycle(): void
    {
        $admin = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Platform Admin');
        $target = $this->makePlatformUser(RoleKey::SUPPORT_AGENT, 'Target User');
        $session = $this->createAuthenticatedSession($admin);

        $this->putJson('/api/v1/users/'.$target->user_id, [
            'status' => UserStatus::BANNED->value,
        ], $session['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_lifting_one_ban_does_not_unban_user_while_another_ban_is_active(): void
    {
        $admin = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Platform Admin');
        $target = $this->makePlatformUser(RoleKey::SUPPORT_AGENT, 'Banned User', UserStatus::BANNED);

        $firstBan = SecurityBan::query()->create([
            'user_id' => $target->user_id,
            'banned_by' => $admin->user_id,
            'ban_type' => 'PERMANENT',
            'ban_reason' => 'First active ban',
            'start_at' => now('Asia/Aden'),
            'end_at' => null,
        ]);
        SecurityBan::query()->create([
            'user_id' => $target->user_id,
            'banned_by' => $admin->user_id,
            'ban_type' => 'PERMANENT',
            'ban_reason' => 'Second active ban',
            'start_at' => now('Asia/Aden'),
            'end_at' => null,
        ]);
        $session = $this->createAuthenticatedSession($admin);

        $this->deleteJson('/api/v1/security/bans/'.$firstBan->ban_id, [], $session['headers'])
            ->assertOk();

        $this->assertSame(UserStatus::BANNED, $target->fresh()->status);
    }

    public function test_operational_role_cannot_access_platform_user_administration(): void
    {
        $operationalUser = $this->makeOperationalUser('Network Owner');
        $session = $this->createAuthenticatedSession($operationalUser);

        $this->getJson('/api/v1/users', $session['headers'])->assertForbidden();
        $this->getJson('/api/v1/roles', $session['headers'])->assertForbidden();
    }

    public function test_platform_admin_cannot_read_or_mutate_operational_user_through_platform_routes(): void
    {
        $admin = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Platform Admin');
        $operationalUser = $this->makeOperationalUser('Network Owner');
        $session = $this->createAuthenticatedSession($admin);

        $this->getJson('/api/v1/users', $session['headers'])->assertOk()->assertDontSee('Network Owner');
        $operationalUser->delete();
        $this->getJson('/api/v1/users/trashed', $session['headers'])->assertOk()->assertDontSee('Network Owner');

        $operationalUser->restore();
        $this->getJson('/api/v1/users/'.$operationalUser->user_id, $session['headers'])->assertForbidden();
        $this->getJson('/api/v1/security/users/'.$operationalUser->user_id.'/sessions', $session['headers'])->assertForbidden();
        $this->getJson('/api/v1/security/users/'.$operationalUser->user_id.'/devices', $session['headers'])->assertForbidden();
        $this->postJson('/api/v1/security/ban-user', [
            'user_id' => $operationalUser->user_id,
            'ban_type' => 'PERMANENT',
            'ban_reason' => 'Cross-scope regression attempt',
        ], $session['headers'])->assertForbidden();
        $this->putJson('/api/v1/users/'.$operationalUser->user_id, [
            'user_full_name' => 'Attempted Cross Scope Edit',
        ], $session['headers'])->assertForbidden();
        $this->postJson('/api/v1/users/batch-delete', [
            'ids' => [$admin->user_id, $operationalUser->user_id],
        ], $session['headers'])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'user_id' => $operationalUser->user_id,
            'user_full_name' => 'Network Owner',
            'deleted_at' => null,
        ]);
    }



    public function test_last_active_super_admin_cannot_be_disabled_deleted_or_banned(): void
    {
        $admin = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Only Platform Admin');
        $adminRole = Role::byKey(RoleKey::SUPER_ADMIN)->firstOrFail();
        User::query()
            ->where('role_id', $adminRole->role_id)
            ->where('user_id', '!=', $admin->user_id)
            ->update(['status' => UserStatus::INACTIVE->value]);
        $freshAdmin = $admin->fresh();
        $this->assertSame((int) $adminRole->role_id, (int) $freshAdmin->role_id);
        $this->assertSame(UserStatus::ACTIVE, $freshAdmin->status);
        $this->assertNull($freshAdmin->deleted_at);
        try {
            app(\App\Services\PlatformAdminContinuityService::class)->assertMutationIsSafe(
                $adminRole,
                $freshAdmin,
                nextStatus: UserStatus::INACTIVE,
            );
            $this->fail('The continuity guard must reject disabling the last active SUPER_ADMIN.');
        } catch (ValidationException) {
            // Expected: the final active platform administrator is protected.
        }
        $session = $this->createAuthenticatedSession($admin);

        $this->postJson('/api/v1/users/'.$admin->user_id.'/status', [
            'status' => UserStatus::INACTIVE->value,
        ], $session['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->deleteJson('/api/v1/users/'.$admin->user_id, [], $session['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->postJson('/api/v1/security/ban-user', [
            'user_id' => $admin->user_id,
            'ban_type' => 'PERMANENT',
            'ban_reason' => 'Last admin protection test',
        ], $session['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseHas('users', [
            'user_id' => $admin->user_id,
            'status' => UserStatus::ACTIVE->value,
            'deleted_at' => null,
        ]);
    }

    public function test_super_admin_can_be_demoted_when_another_active_super_admin_remains(): void
    {
        $first = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'First Platform Admin');
        $second = $this->makePlatformUser(RoleKey::SUPER_ADMIN, 'Second Platform Admin');
        $supportRole = Role::byKey(RoleKey::SUPPORT_AGENT)->firstOrFail();
        $session = $this->createAuthenticatedSession($first);

        $this->putJson('/api/v1/users/'.$second->user_id, [
            'role_id' => $supportRole->role_id,
        ], $session['headers'])->assertOk();

        $this->assertDatabaseHas('users', [
            'user_id' => $first->user_id,
            'role_id' => $first->role_id,
            'status' => UserStatus::ACTIVE->value,
        ]);
        $this->assertDatabaseHas('users', [
            'user_id' => $second->user_id,
            'role_id' => $supportRole->role_id,
            'status' => UserStatus::ACTIVE->value,
        ]);
    }

    private function makeOperationalUser(string $name): User
    {
        $networkRole = Role::byKey(RoleKey::NETWORK_OWNER)->firstOrFail();
        $governorate = Governorate::first()
            ?? Governorate::query()->create(['governorate_name' => 'Platform Scope Test Governorate']);
        $directorate = Directorate::query()->firstOrCreate(
            [
                'governorate_id' => $governorate->governorate_id,
                'directorate_name' => 'Platform Scope Test Directorate',
            ]
        );

        $user = User::query()->create([
            'role_id' => null,
            'user_full_name' => $name,
            'user_phone_number' => '77'.random_int(1000000, 9999999),
            'user_password' => Hash::make('SecurePassword123'),
            'status' => UserStatus::ACTIVE,
        ]);

        $network = Network::query()->create([
            'owner_id' => $user->user_id,
            'network_name' => 'Scope Test '.bin2hex(random_bytes(4)),
            'network_phone_number' => '78'.random_int(1000000, 9999999),
            'governorate_id' => $governorate->governorate_id,
            'directorate_id' => $directorate->directorate_id,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::query()->create([
            'network_id' => $network->network_id,
            'user_id' => $user->user_id,
            'role_id' => $networkRole->role_id,
            'status' => 'ACTIVE',
        ]);

        return $user;
    }

    private function makePlatformUser(
        RoleKey $roleKey,
        string $name,
        UserStatus $status = UserStatus::ACTIVE,
    ): User {
        $role = Role::byKey($roleKey)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->role_id,
            'user_full_name' => $name,
            'user_phone_number' => '77'.random_int(1000000, 9999999),
            'user_password' => Hash::make('SecurePassword123'),
            'status' => $status,
        ]);
    }
}
