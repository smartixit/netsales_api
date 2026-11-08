<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\SecurityBan;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
