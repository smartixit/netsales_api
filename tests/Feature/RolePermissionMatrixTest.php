<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Models\Role;
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
