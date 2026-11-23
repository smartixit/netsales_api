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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSensitiveServicesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_agent_cannot_access_global_backup_sms_or_scheduler_services(): void
    {
        $support = $this->makeUserWithRole(RoleKey::SUPPORT_AGENT);
        $headers = $this->createAuthenticatedSession($support)['headers'];

        $this->getJson('/api/v1/backups/settings', $headers)->assertForbidden();
        $this->getJson('/api/v1/sms-settings', $headers)->assertForbidden();
        $this->getJson('/api/v1/scheduled-tasks', $headers)->assertForbidden();
        $this->getJson('/api/v1/scheduled-tasks/stats', $headers)->assertForbidden();
        $this->postJson('/api/v1/scheduled-tasks/catchup', [], $headers)->assertForbidden();
    }

    public function test_platform_accountant_cannot_change_global_sms_or_backup_configuration(): void
    {
        $accountant = $this->makeUserWithRole(RoleKey::ACCOUNTANT);
        $headers = $this->createAuthenticatedSession($accountant)['headers'];

        $this->putJson('/api/v1/sms-settings/global', [], $headers)->assertForbidden();
        $this->putJson('/api/v1/sms-settings/networks/1', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/sms-settings/networks/1/test', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/backups/drive/token', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/backups/drive/disconnect', [], $headers)->assertForbidden();
        $this->putJson('/api/v1/backups/config', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/backups/run', [], $headers)->assertForbidden();
    }

    public function test_operational_network_owner_cannot_access_platform_global_services(): void
    {
        $owner = $this->makeUserWithRole(RoleKey::NETWORK_OWNER);
        $headers = $this->createAuthenticatedSession($owner)['headers'];

        $this->getJson('/api/v1/backups', $headers)->assertForbidden();
        $this->getJson('/api/v1/sms-settings', $headers)->assertForbidden();
        $this->getJson('/api/v1/scheduled-tasks', $headers)->assertForbidden();
        $this->postJson('/api/v1/admin/notifications/send', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/admin/notifications/user', [], $headers)->assertForbidden();
    }

    public function test_admin_notification_routes_require_platform_scope_and_create_permission(): void
    {
        $accountant = $this->makeUserWithRole(RoleKey::ACCOUNTANT);
        $headers = $this->createAuthenticatedSession($accountant)['headers'];

        $this->postJson('/api/v1/admin/notifications/send', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/admin/notifications/user', [], $headers)->assertForbidden();
    }

    public function test_platform_accountant_cannot_enter_operational_report_workspace(): void
    {
        $accountant = $this->makeUserWithRole(RoleKey::ACCOUNTANT);
        $headers = $this->createAuthenticatedSession($accountant)['headers'];

        $this->getJson('/api/v1/reports/catalog', $headers)->assertForbidden();
    }

    public function test_bank_account_management_is_restricted_to_platform_finance_roles(): void
    {
        $accountant = $this->makeUserWithRole(RoleKey::ACCOUNTANT);
        $accountantHeaders = $this->createAuthenticatedSession($accountant)['headers'];

        $this->getJson('/api/v1/bank-accounts', $accountantHeaders)->assertOk();

        // The accountant may manage ordinary bank-account operations but may
        // not permanently erase financial account records.
        $this->deleteJson('/api/v1/bank-accounts/1/force', [], $accountantHeaders)
            ->assertForbidden();

        $networkOwner = $this->makeUserWithRole(RoleKey::NETWORK_OWNER);
        $networkHeaders = $this->createAuthenticatedSession($networkOwner)['headers'];

        $this->getJson('/api/v1/bank-accounts', $networkHeaders)->assertForbidden();
        $this->postJson('/api/v1/bank-accounts', [], $networkHeaders)->assertForbidden();
    }

    private function makeUserWithRole(RoleKey $roleKey): User
    {
        $role = Role::byKey($roleKey)->firstOrFail();
        $phoneNumber = '77'.str_pad((string) $role->role_id, 7, '0', STR_PAD_LEFT);

        $user = new User([
            'role_id' => $role->scope === RoleScope::PLATFORM ? $role->role_id : null,
            'user_full_name' => 'Authorization Test '.$roleKey->value,
            'user_phone_number' => $phoneNumber,
            'user_password' => 'AuthorizationTestPassword',
            'status' => UserStatus::ACTIVE,
        ]);

        $user->save();

        if ($role->scope === RoleScope::NETWORK) {
            $governorate = Governorate::query()->firstOrFail();
            $directorate = Directorate::query()
                ->where('governorate_id', $governorate->governorate_id)
                ->firstOrFail();

            $network = Network::query()->create([
                'owner_id' => $user->user_id,
                'network_name' => 'Authorization Test Network '.$user->user_id,
                'network_phone_number' => $phoneNumber,
                'governorate_id' => $governorate->governorate_id,
                'directorate_id' => $directorate->directorate_id,
                'status' => 'ACTIVE',
            ]);

            NetworkUser::query()->create([
                'network_id' => $network->network_id,
                'user_id' => $user->user_id,
                'role_id' => $role->role_id,
                'status' => 'ACTIVE',
            ]);
        }

        return $user;
    }
}
