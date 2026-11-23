<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Services\TenantPermissionService;
use App\Services\Billing\BillingAuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use App\Services\UserService;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_roles_keep_their_declared_scope(): void
    {
        $expectedScopes = [
            RoleKey::SUPER_ADMIN->value => RoleScope::PLATFORM,
            RoleKey::SUPPORT_AGENT->value => RoleScope::PLATFORM,
            RoleKey::ACCOUNTANT->value => RoleScope::PLATFORM,
            RoleKey::NETWORK_OWNER->value => RoleScope::NETWORK,
            RoleKey::NETWORK_USER->value => RoleScope::NETWORK,
            RoleKey::NETWORK_ACCOUNTANT->value => RoleScope::NETWORK,
            RoleKey::POS_AGENT->value => RoleScope::POS,
            RoleKey::POS_USER->value => RoleScope::POS,
            RoleKey::POS_ACCOUNTANT->value => RoleScope::POS,
        ];

        foreach ($expectedScopes as $roleKey => $expectedScope) {
            $role = Role::query()->where('role_key', $roleKey)->firstOrFail();

            $this->assertSame($expectedScope, $role->scope, "Unexpected scope for [{$roleKey}].");
        }
    }

    public function test_platform_accountant_is_limited_to_platform_finance(): void
    {
        $permissions = $this->permissionKeys(RoleKey::ACCOUNTANT);

        $this->assertContains('dashboard.view', $permissions);
        $this->assertContains('bank_accounts.view', $permissions);
        $this->assertContains('bank_accounts.create', $permissions);
        $this->assertContains('billing.view', $permissions);
        $this->assertContains('billing.verify_payment', $permissions);
        $this->assertContains('billing.refund_payment', $permissions);
        $this->assertContains('billing.issue_credit_note', $permissions);

        $this->assertNotContains('networks.view', $permissions);
        $this->assertNotContains('pos.view', $permissions);
        $this->assertNotContains('network_wallets.view', $permissions);
        $this->assertNotContains('sales.view', $permissions);
        $this->assertNotContains('expenses.view', $permissions);
        $this->assertNotContains('requests.approve', $permissions);
        $this->assertNotContains('settings.edit', $permissions);
    }

        public function test_network_owner_can_toggle_package_status_but_network_user_and_accountant_cannot(): void
    {
        $ownerPermissions = $this->permissionKeys(RoleKey::NETWORK_OWNER);
        $accountantPermissions = $this->permissionKeys(RoleKey::NETWORK_ACCOUNTANT);
        $userPermissions = $this->permissionKeys(RoleKey::NETWORK_USER);

        $this->assertContains('voucher_packages.toggle_status', $ownerPermissions);
        $this->assertNotContains('voucher_packages.toggle_status', $accountantPermissions);
        $this->assertNotContains('voucher_packages.toggle_status', $userPermissions);

        $this->assertContains('voucher_package_discounts.toggle_status', $ownerPermissions);
        $this->assertNotContains('voucher_package_discounts.toggle_status', $accountantPermissions);
        $this->assertNotContains('voucher_package_discounts.toggle_status', $userPermissions);
    }

public function test_network_accountant_gets_operational_finance_but_not_approval_or_lifecycle_privileges(): void
    {
        $permissions = $this->permissionKeys(RoleKey::NETWORK_ACCOUNTANT);

        $this->assertNotContains('dashboard.view', $permissions);
        $this->assertContains('network_wallets.report', $permissions);
        $this->assertContains('sales.report', $permissions);
        $this->assertContains('expenses.view', $permissions);
        $this->assertContains('expenses.create', $permissions);
        $this->assertContains('expenses.edit', $permissions);
        $this->assertContains('expenses.export', $permissions);

        $this->assertNotContains('requests.approve', $permissions);
        $this->assertNotContains('requests.reject', $permissions);
        $this->assertNotContains('networks.create_pos_link', $permissions);
        $this->assertNotContains('network_wallets.withdraw', $permissions);
        $this->assertNotContains('expenses.delete', $permissions);
        $this->assertNotContains('expenses.restore', $permissions);
        $this->assertNotContains('expenses.force_delete', $permissions);
        $this->assertNotContains('billing.view', $permissions);
    }

    public function test_network_owner_can_manage_network_tickets_but_network_user_cannot(): void
    {
        $ownerPermissions = $this->permissionKeys(RoleKey::NETWORK_OWNER);
        $userPermissions = $this->permissionKeys(RoleKey::NETWORK_USER);

        foreach (['support.edit', 'support.delete', 'support.restore', 'support.force_delete'] as $permission) {
            $this->assertContains($permission, $ownerPermissions);
            $this->assertNotContains($permission, $userPermissions);
        }
    }

    public function test_network_user_cannot_approve_requests_or_change_financial_records(): void
    {
        $permissions = $this->permissionKeys(RoleKey::NETWORK_USER);

        $this->assertContains('requests.view', $permissions);
        $this->assertContains('sales.view', $permissions);
        $this->assertNotContains('requests.approve', $permissions);
        $this->assertNotContains('requests.reject', $permissions);
        $this->assertNotContains('sales.create', $permissions);
        $this->assertNotContains('sales.edit', $permissions);
        $this->assertNotContains('expenses.create', $permissions);
        $this->assertNotContains('network_wallets.withdraw', $permissions);
    }

    public function test_pos_agent_can_confirm_pos_linking_but_cannot_approve_network_requests(): void
    {
        $permissions = $this->permissionKeys(RoleKey::POS_AGENT);

        $this->assertNotContains('dashboard.view', $permissions);
        $this->assertContains('pos.edit', $permissions);
        $this->assertContains('pos.preview_link_challenge', $permissions);
        $this->assertContains('pos.confirm_link_challenge', $permissions);
        $this->assertContains('requests.create', $permissions);
        $this->assertContains('requests.cancel', $permissions);
        $this->assertContains('sales.create', $permissions);

        $this->assertNotContains('requests.approve', $permissions);
        $this->assertNotContains('requests.reject', $permissions);
        $this->assertNotContains('pos.approve', $permissions);
        $this->assertNotContains('pos.reject', $permissions);
        $this->assertNotContains('sales.force_delete', $permissions);
        $this->assertNotContains('network_wallets.withdraw', $permissions);
        $this->assertNotContains('billing.view', $permissions);
    }

    public function test_pos_user_and_pos_accountant_do_not_receive_pos_owner_or_network_expense_mutations(): void
    {
        $posUserPermissions = $this->permissionKeys(RoleKey::POS_USER);
        $posAccountantPermissions = $this->permissionKeys(RoleKey::POS_ACCOUNTANT);

        $this->assertNotContains('pos.edit', $posUserPermissions);
        $this->assertNotContains('requests.edit', $posUserPermissions);
        $this->assertNotContains('sales.delete', $posUserPermissions);
        $this->assertNotContains('pos.confirm_link_challenge', $posUserPermissions);

        $this->assertContains('sales.report', $posAccountantPermissions);
        $this->assertContains('pos_wallets.report', $posAccountantPermissions);
        $this->assertNotContains('expenses.view', $posAccountantPermissions);
        $this->assertNotContains('expenses.create', $posAccountantPermissions);
        $this->assertNotContains('requests.approve', $posAccountantPermissions);
        $this->assertNotContains('sales.delete', $posAccountantPermissions);
    }

    public function test_direct_permissions_cannot_be_assigned_to_operational_users(): void
    {
        $networkUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Operational Direct Grant '.uniqid(),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $permission = Permission::query()
            ->where('permission_key', 'billing.refund_payment')
            ->where('status', true)
            ->firstOrFail();

        try {
            app(UserService::class)->syncPermissions(
                (int) $networkUser->user_id,
                [(int) $permission->permission_id]
            );

            $this->fail('Operational users must not receive unscoped direct permissions.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user_id', $exception->errors());
        }

        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $networkUser->user_id,
            'permission_id' => $permission->permission_id,
        ]);
    }

    public function test_legacy_direct_grant_is_not_effective_for_operational_user(): void
    {
        $networkUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Legacy Direct Grant '.uniqid(),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $permission = Permission::query()
            ->where('permission_key', 'billing.refund_payment')
            ->where('status', true)
            ->firstOrFail();

        // Simulate historical data that predates the strict scope boundary.
        $networkUser->permissions()->attach($permission->permission_id);

        $freshUser = User::query()->findOrFail($networkUser->user_id);

        $this->assertFalse($freshUser->hasPermission('billing.refund_payment'));
        $this->assertFalse(
            app(TenantPermissionService::class)->allowsPlatform($freshUser, 'billing.refund_payment')
        );
    }

    public function test_platform_user_can_receive_an_active_direct_permission(): void
    {
        $platformRole = Role::query()
            ->where('role_key', RoleKey::SUPPORT_AGENT->value)
            ->firstOrFail();

        $platformUser = $this->createUserWithScope(RoleScope::PLATFORM, [
            'role_id' => $platformRole->role_id,
            'user_full_name' => 'Platform Direct Grant '.uniqid(),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $permission = Permission::query()
            ->where('permission_key', 'bank_accounts.force_delete')
            ->where('status', true)
            ->firstOrFail();

        $updatedUser = app(UserService::class)->syncPermissions(
            (int) $platformUser->user_id,
            [(int) $permission->permission_id]
        );

        $this->assertTrue($updatedUser->hasPermission('bank_accounts.force_delete'));
        $this->assertTrue(
            app(TenantPermissionService::class)->allowsPlatform($updatedUser, 'bank_accounts.force_delete')
        );
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $platformUser->user_id,
            'permission_id' => $permission->permission_id,
        ]);
    }

    public function test_super_admin_bypass_requires_active_platform_root_role(): void
    {
        $role = Role::query()
            ->where('role_key', RoleKey::SUPER_ADMIN->value)
            ->firstOrFail();

        $inactiveUser = $this->createUserWithScope(RoleScope::PLATFORM, [
            'role_id' => $role->role_id,
            'user_full_name' => 'Inactive Super Admin '.uniqid(),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => 'INACTIVE',
        ]);

        $this->assertFalse($inactiveUser->isSuperAdmin());

        $activeUser = $this->createUserWithScope(RoleScope::PLATFORM, [
            'role_id' => $role->role_id,
            'user_full_name' => 'Disabled Role Super Admin '.uniqid(),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $role->status = false;
        $role->save();

        $this->assertFalse($activeUser->fresh()->isSuperAdmin());

        $role->status = true;
        $role->save();

        $freshSuperAdmin = $activeUser->fresh();

        $this->assertTrue($freshSuperAdmin->isSuperAdmin());
        $this->assertTrue(
            app(TenantPermissionService::class)->allowsPlatform($freshSuperAdmin, 'dashboard.view')
        );
        $this->assertTrue(
            app(TenantPermissionService::class)->allowsPlatform($freshSuperAdmin, 'networks.view')
        );
        $this->assertFalse(
            app(TenantPermissionService::class)->allowsPlatform($freshSuperAdmin, 'networks.edit')
        );
        $this->assertFalse(
            app(TenantPermissionService::class)->allowsPlatform($freshSuperAdmin, 'pos.delete')
        );
        $this->assertFalse(
            app(TenantPermissionService::class)->allowsPlatform($freshSuperAdmin, 'sales.delete')
        );
        $this->assertFalse($freshSuperAdmin->hasPermission('sales.delete'));
    }





    public function test_retired_or_unimplemented_permissions_are_not_seeded_as_active(): void
    {
        $retiredKeys = [
            'users.export',
            'networks.report',
            'networks.export',
            'pos.report',
            'pos.export',
            'voucher_packages.export',
            'voucher_package_discounts.export',
        ];

        foreach ($retiredKeys as $permissionKey) {
            $this->assertDatabaseMissing('permissions', [
                'permission_key' => $permissionKey,
                'status' => true,
            ]);
        }
    }

    public function test_platform_user_cannot_receive_tenant_scoped_direct_permission(): void
    {
        $platformRole = Role::query()
            ->where('role_key', RoleKey::SUPPORT_AGENT->value)
            ->firstOrFail();
        $platformUser = $this->createUserWithScope(RoleScope::PLATFORM, [
            'role_id' => $platformRole->role_id,
            'user_full_name' => 'Platform Tenant Grant '.uniqid(),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $permission = Permission::query()
            ->where('permission_key', 'sales.delete')
            ->where('status', true)
            ->firstOrFail();

        try {
            app(UserService::class)->syncPermissions(
                (int) $platformUser->user_id,
                [(int) $permission->permission_id]
            );

            $this->fail('A tenant-scoped permission must not be converted into a global platform grant.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permissions', $exception->errors());
        }

        $this->assertFalse($platformUser->fresh()->hasPermission('sales.delete'));
        $this->assertFalse(
            app(TenantPermissionService::class)->allowsPlatform($platformUser, 'sales.delete')
        );
    }

    public function test_legacy_tenant_scoped_direct_grant_is_ignored_for_platform_identity(): void
    {
        $platformRole = Role::query()
            ->where('role_key', RoleKey::SUPPORT_AGENT->value)
            ->firstOrFail();
        $platformUser = $this->createUserWithScope(RoleScope::PLATFORM, [
            'role_id' => $platformRole->role_id,
            'user_full_name' => 'Legacy Platform Tenant Grant '.uniqid(),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $permission = Permission::query()
            ->where('permission_key', 'sales.delete')
            ->where('status', true)
            ->firstOrFail();
        $platformUser->permissions()->attach($permission->permission_id);

        $freshPlatformUser = User::query()->findOrFail($platformUser->user_id);

        $this->assertFalse($freshPlatformUser->hasPermission('sales.delete'));
        $this->assertFalse(
            app(TenantPermissionService::class)->allowsPlatform($freshPlatformUser, 'sales.delete')
        );
    }

    public function test_network_ownership_does_not_bypass_a_missing_role_permission(): void
    {
        $owner = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Network Owner Authorization '.uniqid(),
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $networkOwnerRole = Role::query()
            ->where('role_key', RoleKey::NETWORK_OWNER->value)
            ->firstOrFail();
        $network = Network::query()->create([
            'owner_id' => $owner->user_id,
            'network_name' => 'Network Permission Gate '.$owner->user_id,
            'network_phone_number' => $owner->user_phone_number,
            'governorate_id' => \App\Models\Governorate::query()->value('governorate_id'),
            'directorate_id' => \App\Models\Directorate::query()->value('directorate_id'),
            'status' => 'ACTIVE',
        ]);

        NetworkUser::query()->create([
            'network_id' => $network->network_id,
            'user_id' => $owner->user_id,
            'role_id' => $networkOwnerRole->role_id,
            'status' => 'ACTIVE',
            'last_event_seq' => 1,
        ]);

        $permission = Permission::query()
            ->where('permission_key', 'sales.report')
            ->where('status', true)
            ->firstOrFail();
        $networkOwnerRole->permissions()->detach($permission->permission_id);

        $this->assertFalse(
            app(TenantPermissionService::class)->allowsNetwork(
                $owner,
                (int) $network->network_id,
                'sales.report'
            )
        );

        try {
            app(BillingAuthorizationService::class)->assertNetworkPermission(
                $owner,
                (int) $network->network_id,
                'sales.report'
            );

            $this->fail('Network ownership must not bypass a missing operation grant.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    /**
     * @return array<int, string>
     */
    private function permissionKeys(RoleKey $roleKey): array
    {
        $role = Role::query()->where('role_key', $roleKey->value)->firstOrFail();

        return $role->permissions()
            ->where('permissions.status', true)
            ->orderBy('permission_key')
            ->pluck('permission_key')
            ->all();
    }
}
