<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RolePermissionSeeder extends Seeder
{
    /**
     * Keep role grants explicit. The scope assertion below prevents assigning
     * a permission from another workspace even if a key is accidentally added
     * to the wrong role list.
     */
    public function run(): void
    {
        $roles = DB::table('roles')->get()->keyBy('role_key');
        $permissions = DB::table('permissions')
            ->where('status', true)
            ->get()
            ->keyBy('permission_key');

        $definitions = [
            RoleKey::SUPER_ADMIN->value => [
                'scope' => RoleScope::PLATFORM->value,
                'permissions' => null,
            ],
            RoleKey::SUPPORT_AGENT->value => [
                'scope' => RoleScope::PLATFORM->value,
                'permissions' => [
                    'users.view',
                    'networks.view',
                    'pos.view',
                    'support_tickets.view',
                    'support_tickets.update',
                    'support_tickets.reassign',
                    'support_tickets.reply',
                    'support_tickets.mark_read',
                    'support_tickets.view_trashed',
                ],
            ],
            RoleKey::ACCOUNTANT->value => [
                'scope' => RoleScope::PLATFORM->value,
                'permissions' => [
                    'networks.view',
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'pos.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'requests.view',
                    'requests.report',
                    'requests.export',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.report',
                    'expenses.export',
                    'billing.view',
                    'billing.report',
                    'billing.export',
                    'billing.verify_payment',
                    'billing.reject_payment',
                    'billing.issue_credit_note',
                    'billing.refund_payment',
                    'billing.void_invoice',
                    'billing.manage_treasury_accounts',
                    'billing.manage_exchange_rates',
                    'billing.convert_currency',
                ],
            ],
            RoleKey::NETWORK_OWNER->value => [
                'scope' => RoleScope::NETWORK->value,
                'permissions' => [
                    'networks.view',
                    'networks.update',
                    'networks.delete',
                    'network_members.view_members',
                    'network_members.add_member',
                    'network_members.change_member_role',
                    'network_members.change_member_status',
                    'network_members.remove_member',
                    'network_pos.view',
                    'network_pos.create',
                    'network_pos.update',
                    'network_pos.delete',
                    'network_pos.bulk_delete',
                    'network_pos.toggle_status',
                    'network_pos.bulk_toggle_status',
                    'network_pos.search_linkable_pos',
                    'network_pos.resolve_link_target',
                    'network_pos.invite_pos',
                    'network_pos.revoke_invitation',
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'network_wallets.create_wallet',
                    'network_wallets.deposit',
                    'network_wallets.withdraw',
                    'network_wallets.update_wallet_status',
                    'pos.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'pos_wallets.create_wallet',
                    'pos_wallets.deposit',
                    'pos_wallets.withdraw',
                    'pos_wallets.transfer_from_network',
                    'pos_wallets.transfer_to_network',
                    'pos_wallets.manage_credit_limit',
                    'pos_wallets.update_wallet_status',
                    'voucher_packages.view',
                    'voucher_packages.create',
                    'voucher_packages.update',
                    'voucher_packages.delete',
                    'voucher_packages.restore',
                    'voucher_packages.force_delete',
                    'voucher_packages.bulk_delete',
                    'voucher_packages.bulk_restore',
                    'voucher_packages.bulk_force_delete',
                    'voucher_packages.toggle_status',
                    'package_discounts.view',
                    'package_discounts.create',
                    'package_discounts.update',
                    'package_discounts.delete',
                    'package_discounts.restore',
                    'package_discounts.force_delete',
                    'package_discounts.bulk_delete',
                    'package_discounts.bulk_restore',
                    'package_discounts.bulk_force_delete',
                    'package_discounts.toggle_status',
                    'requests.view',
                    'requests.delete',
                    'requests.bulk_delete',
                    'requests.bulk_restore',
                    'requests.bulk_force_delete',
                    'requests.restore',
                    'requests.force_delete',
                    'requests.preview_modification',
                    'requests.approve',
                    'requests.reject',
                    'requests.report',
                    'requests.export',
                    'sales.view',
                    'sales.create',
                    'sales.update',
                    'sales.delete',
                    'sales.force_delete',
                    'sales.bulk_delete',
                    'sales.bulk_force_delete',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.create',
                    'expenses.update',
                    'expenses.delete',
                    'expenses.restore',
                    'expenses.force_delete',
                    'expenses.bulk_delete',
                    'expenses.bulk_restore',
                    'expenses.bulk_force_delete',
                    'expenses.add_item',
                    'expenses.delete_item',
                    'expenses.delete_attachment',
                    'expenses.report',
                    'expenses.export',
                    'support_tickets.view',
                    'support_tickets.create',
                    'support_tickets.update',
                    'support_tickets.reassign',
                    'support_tickets.reply',
                    'support_tickets.mark_read',
                ],
            ],
            RoleKey::NETWORK_USER->value => [
                'scope' => RoleScope::NETWORK->value,
                'permissions' => [
                    'networks.view',
                    'network_pos.view',
                    'network_wallets.view',
                    'pos.view',
                    'pos_wallets.view',
                    'voucher_packages.view',
                    'package_discounts.view',
                    'requests.view',
                    'requests.preview_modification',
                    'requests.approve',
                    'requests.reject',
                    'requests.report',
                    'sales.view',
                    'sales.report',
                    'expenses.view',
                    'expenses.report',
                    'expenses.export',
                    'support_tickets.view',
                    'support_tickets.create',
                    'support_tickets.reply',
                    'support_tickets.mark_read',
                ],
            ],
            RoleKey::NETWORK_ACCOUNTANT->value => [
                'scope' => RoleScope::NETWORK->value,
                'permissions' => [
                    'networks.view',
                    'network_pos.view',
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'pos.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'package_discounts.view',
                    'requests.view',
                    'requests.report',
                    'requests.export',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.report',
                    'expenses.export',
                    'support_tickets.view',
                ],
            ],
            RoleKey::POS_AGENT->value => [
                'scope' => RoleScope::POS->value,
                'permissions' => [
                    'pos.view',
                    'pos.update',
                    'pos_members.view_members',
                    'pos_members.add_member',
                    'pos_members.change_member_role',
                    'pos_members.change_member_status',
                    'pos_members.remove_member',
                    'networks.view',
                    'network_pos.view',
                    'network_pos.preview_invitation',
                    'network_pos.confirm_invitation',
                    'network_pos.view_invitation_status',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'package_discounts.view',
                    'requests.view',
                    'requests.create',
                    'requests.quote',
                    'requests.update',
                    'requests.cancel',
                    'requests.delete',
                    'requests.bulk_delete',
                    'requests.report',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'support_tickets.view',
                    'support_tickets.create',
                    'support_tickets.update',
                    'support_tickets.reply',
                    'support_tickets.mark_read',
                ],
            ],
            RoleKey::POS_USER->value => [
                'scope' => RoleScope::POS->value,
                'permissions' => [
                    'pos.view',
                    'networks.view',
                    'network_pos.view',
                    'network_pos.preview_invitation',
                    'network_pos.confirm_invitation',
                    'network_pos.view_invitation_status',
                    'pos_wallets.view',
                    'voucher_packages.view',
                    'package_discounts.view',
                    'requests.view',
                    'requests.create',
                    'requests.quote',
                    'sales.view',
                    'support_tickets.view',
                    'support_tickets.create',
                    'support_tickets.reply',
                    'support_tickets.mark_read',
                ],
            ],
            RoleKey::POS_ACCOUNTANT->value => [
                'scope' => RoleScope::POS->value,
                'permissions' => [
                    'pos.view',
                    'networks.view',
                    'network_pos.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'package_discounts.view',
                    'requests.view',
                    'requests.report',
                    'requests.export',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'support_tickets.view',
                ],
            ],
        ];

        $seededRoleIds = [];

        foreach ($definitions as $roleKey => $definition) {
            $role = $roles[$roleKey] ?? null;
            if ($role === null) {
                throw new RuntimeException("Required system role [{$roleKey}] is missing.");
            }

            if ($role->scope !== $definition['scope']) {
                throw new RuntimeException("Role [{$roleKey}] has invalid scope [{$role->scope}].");
            }

            $seededRoleIds[] = (int) $role->role_id;

            if ($roleKey === RoleKey::SUPER_ADMIN->value) {
                $rolePermissionIds = $permissions
                    ->filter(fn ($permission): bool => $permission->scope === RoleScope::PLATFORM->value)
                    ->pluck('permission_id')
                    ->map(fn ($permissionId): int => (int) $permissionId)
                    ->all();
            } else {
                $rolePermissionIds = [];

                foreach ($definition['permissions'] as $permissionKey) {
                    $permission = $permissions[$permissionKey] ?? null;
                    if ($permission === null) {
                        throw new RuntimeException("Permission [{$permissionKey}] required by role [{$roleKey}] is missing or inactive.");
                    }

                    $allowedScopes = match ($definition['scope']) {
                        RoleScope::PLATFORM->value => [
                            RoleScope::PLATFORM->value,
                            'SHARED',
                        ],
                        RoleScope::NETWORK->value => [
                            RoleScope::NETWORK->value,
                            'SHARED',
                        ],
                        RoleScope::POS->value => [
                            RoleScope::POS->value,
                            'SHARED',
                        ],
                        default => [],
                    };

                    if (! in_array($permission->scope, $allowedScopes, true)) {
                        throw new RuntimeException(
                            "Permission [{$permissionKey}] scope [{$permission->scope}] cannot be assigned to role [{$roleKey}]."
                        );
                    }

                    $rolePermissionIds[] = (int) $permission->permission_id;
                }
            }

            DB::table('role_permissions')
                ->where('role_id', $role->role_id)
                ->delete();

            $now = now('Asia/Aden');
            $insertData = array_map(
                fn (int $permissionId): array => [
                    'role_id' => (int) $role->role_id,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                array_values(array_unique($rolePermissionIds))
            );

            if ($insertData !== []) {
                DB::table('role_permissions')->insert($insertData);
            }
        }

        DB::table('role_permissions')
            ->whereNotIn('role_id', $seededRoleIds)
            ->whereIn('role_id', DB::table('roles')->where('is_system', true)->pluck('role_id'))
            ->delete();
    }
}
