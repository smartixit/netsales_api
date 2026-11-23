<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Services\PlatformPermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RolePermissionSeeder extends Seeder
{
    /**
     * Rebuild exact permission grants for every system role.
     *
     * The arrays are intentionally explicit: missing permission keys or a role
     * with an unexpected scope stop seeding instead of silently granting a
     * partial or cross-scope role.
     */
    public function run(): void
    {
        $assignments = [
            RoleKey::SUPER_ADMIN->value => [
                'scope' => RoleScope::PLATFORM->value,
                'permissions' => null, // All active PLATFORM-registry permissions only.
            ],

            RoleKey::SUPPORT_AGENT->value => [
                'scope' => RoleScope::PLATFORM->value,
                'permissions' => [
                    'dashboard.view',
                    'users.view',
                    'networks.view',
                    'pos.view',
                    'support.view',
                    'support.mark_as_read',
                    'support.reply',
                    'support.edit',
                    'notifications.view',
                ],
            ],

            RoleKey::ACCOUNTANT->value => [
                'scope' => RoleScope::PLATFORM->value,
                'permissions' => [
                    'dashboard.view',
                    'bank_accounts.view',
                    'bank_accounts.create',
                    'bank_accounts.edit',
                    'bank_accounts.toggle_status',
                    'bank_accounts.delete',
                    'bank_accounts.restore',
                    'billing.view',
                    'billing.report',
                    'billing.export',
                    'billing.verify_payment',
                    'billing.reject_payment',
                    'billing.issue_credit_note',
                    'billing.refund_payment',
                    'billing.void_invoice',
                    'billing.create_treasury_account',
                    'billing.update_treasury_status',
                    'billing.create_exchange_rate',
                    'billing.convert_currency',
                    'notifications.view',
                ],
            ],

            RoleKey::NETWORK_OWNER->value => [
                'scope' => RoleScope::NETWORK->value,
                'permissions' => [
                    'networks.view',
                    'networks.edit',
                    'networks.create_pos_link',
                    'networks.update_pos_link',
                    'networks.delete_pos_link',
                    'networks.toggle_pos_link',
                    'networks.create_link_challenge',
                    'networks.revoke_link_challenge',
                    'pos.view',
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'pos_wallets.view',
                    'pos_wallets.create',
                    'pos_wallets.edit',
                    'pos_wallets.deposit',
                    'pos_wallets.withdraw',
                    'pos_wallets.transfer_from_network',
                    'pos_wallets.transfer_to_network',
                    'pos_wallets.manage_credit_limit',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'voucher_package_discounts.view',
                    'voucher_packages.create',
                    'voucher_packages.edit',
                    'voucher_packages.toggle_status',
                    'voucher_packages.delete',
                    'voucher_packages.restore',
                    'voucher_packages.force_delete',
                    'voucher_packages.export',
                    'voucher_package_discounts.create',
                    'voucher_package_discounts.edit',
                    'voucher_package_discounts.toggle_status',
                    'voucher_package_discounts.delete',
                    'voucher_package_discounts.restore',
                    'voucher_package_discounts.force_delete',
                    'requests.view',
                    'requests.edit',
                    'requests.cancel',
                    'requests.approve',
                    'requests.preview_modification',
                    'requests.reject',
                    'requests.delete',
                    'requests.restore',
                    'requests.force_delete',
                    'requests.report',
                    'requests.export',
                    'sales.view',
                    'sales.create',
                    'sales.edit',
                    'sales.delete',
                    'sales.restore',
                    'sales.force_delete',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.create',
                    'expenses.edit',
                    'expenses.add_item',
                    'expenses.delete',
                    'expenses.delete_item',
                    'expenses.delete_attachment',
                    'expenses.restore',
                    'expenses.force_delete',
                    'expenses.report',
                    'expenses.export',
                    'support.view',
                    'support.mark_as_read',
                    'support.create',
                    'support.reply',
                    'notifications.view',
                ],
            ],

            RoleKey::NETWORK_USER->value => [
                'scope' => RoleScope::NETWORK->value,
                'permissions' => [
                    'networks.view',
                    'pos.view',
                    'voucher_packages.view',
                    'voucher_package_discounts.view',
                    'requests.view',
                    'sales.view',
                    'support.view',
                    'support.mark_as_read',
                    'support.create',
                    'support.reply',
                    'notifications.view',
                ],
            ],

            RoleKey::NETWORK_ACCOUNTANT->value => [
                'scope' => RoleScope::NETWORK->value,
                'permissions' => [
                    'networks.view',
                    'pos.view',
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'voucher_package_discounts.view',
                    'requests.view',
                    'requests.report',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.create',
                    'expenses.edit',
                    'expenses.add_item',
                    'expenses.report',
                    'expenses.export',
                    'support.view',
                    'support.mark_as_read',
                    'support.create',
                    'support.reply',
                    'notifications.view',
                ],
            ],

            RoleKey::POS_AGENT->value => [
                'scope' => RoleScope::POS->value,
                'permissions' => [
                    'pos.view',
                    'pos.edit',
                    'pos.preview_link_challenge',
                    'pos.confirm_link_challenge',
                    'networks.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'voucher_package_discounts.view',
                    'requests.view',
                    'requests.create',
                    'requests.edit',
                    'requests.cancel',
                    'requests.restore',
                    'requests.delete',
                    'sales.view',
                    'sales.create',
                    'sales.edit',
                    'sales.delete',
                    'sales.restore',
                    'sales.report',
                    'sales.export',
                    'support.view',
                    'support.mark_as_read',
                    'support.create',
                    'support.reply',
                    'notifications.view',
                ],
            ],

            RoleKey::POS_USER->value => [
                'scope' => RoleScope::POS->value,
                'permissions' => [
                    'pos.view',
                    'networks.view',
                    'pos_wallets.view',
                    'voucher_packages.view',
                    'voucher_package_discounts.view',
                    'requests.view',
                    'requests.create',
                    'requests.cancel',
                    'sales.view',
                    'sales.create',
                    'support.view',
                    'support.mark_as_read',
                    'support.create',
                    'support.reply',
                    'notifications.view',
                ],
            ],

            RoleKey::POS_ACCOUNTANT->value => [
                'scope' => RoleScope::POS->value,
                'permissions' => [
                    'pos.view',
                    'networks.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'voucher_package_discounts.view',
                    'requests.view',
                    'requests.report',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'support.view',
                    'support.mark_as_read',
                    'support.create',
                    'support.reply',
                    'notifications.view',
                ],
            ],
        ];

        $roleRows = DB::table('roles')
            ->whereNull('deleted_at')
            ->whereIn('role_key', array_keys($assignments))
            ->get(['role_id', 'role_key', 'scope', 'status'])
            ->keyBy('role_key');

        $missingRoles = array_values(array_diff(array_keys($assignments), $roleRows->keys()->all()));
        if ($missingRoles !== []) {
            throw new RuntimeException(
                'Role permission seeding is blocked; missing system roles: '.implode(', ', $missingRoles)
            );
        }

        $requestedPermissionKeys = [];
        foreach ($assignments as $assignment) {
            if ($assignment['permissions'] !== null) {
                $requestedPermissionKeys = array_merge($requestedPermissionKeys, $assignment['permissions']);
            }
        }

        $permissionRows = DB::table('permissions')
            ->where('status', true)
            ->whereNull('deleted_at')
            ->whereIn('permission_key', array_values(array_unique($requestedPermissionKeys)))
            ->get(['permission_id', 'permission_key'])
            ->keyBy('permission_key');

        $missingPermissions = array_values(array_diff(
            array_values(array_unique($requestedPermissionKeys)),
            $permissionRows->keys()->all()
        ));
        if ($missingPermissions !== []) {
            throw new RuntimeException(
                'Role permission seeding is blocked; missing permission keys: '.implode(', ', $missingPermissions)
            );
        }

        $allActivePermissionIds = DB::table('permissions')
            ->join('modules', 'modules.module_id', '=', 'permissions.module_id')
            ->where('permissions.status', true)
            ->whereNull('permissions.deleted_at')
            ->where('modules.status', true)
            ->whereNull('modules.deleted_at')
            ->where(function ($query): void {
                $query->whereIn('modules.module_key', PlatformPermissionRegistry::MODULE_KEYS)
                    ->orWhereIn(
                        'permissions.permission_key',
                        array_merge(
                            PlatformPermissionRegistry::SHARED_READ_PERMISSION_KEYS,
                            PlatformPermissionRegistry::PLATFORM_PERMISSION_KEYS
                        )
                    );
            })
            ->pluck('permissions.permission_id')
            ->all();

        // Direct permission assignments are global: the pivot contains no
        // network_id or pos_id. Keep them only on active PLATFORM users.
        DB::table('user_permissions')
            ->where(function ($query): void {
                $query->whereNotIn('user_id', function ($platformUsers): void {
                    $platformUsers->select('users.user_id')
                        ->from('users')
                        ->join('roles', 'roles.role_id', '=', 'users.role_id')
                        ->where('roles.scope', RoleScope::PLATFORM->value)
                        ->where('roles.status', true)
                        ->whereNull('roles.deleted_at')
                        ->where('users.status', 'ACTIVE');
                })->orWhereNotIn('permission_id', function ($platformPermissions): void {
                    $platformPermissions->select('permissions.permission_id')
                        ->from('permissions')
                        ->join('modules', 'modules.module_id', '=', 'permissions.module_id')
                        ->where('permissions.status', true)
                        ->whereNull('permissions.deleted_at')
                        ->where(function ($query): void {
                            $query->whereIn('modules.module_key', PlatformPermissionRegistry::MODULE_KEYS)
                                ->orWhereIn(
                                    'permissions.permission_key',
                                    array_merge(
                                        PlatformPermissionRegistry::SHARED_READ_PERMISSION_KEYS,
                                        PlatformPermissionRegistry::PLATFORM_PERMISSION_KEYS
                                    )
                                );
                        });
                });
            })
            ->delete();

        $now = now('Asia/Aden');

        foreach ($assignments as $roleKey => $assignment) {
            $role = $roleRows[$roleKey];

            if ($role->scope !== $assignment['scope']) {
                throw new RuntimeException(
                    "Role [{$roleKey}] has scope [{$role->scope}], expected [{$assignment['scope']}]."
                );
            }

            if (! (bool) $role->status) {
                throw new RuntimeException("System role [{$roleKey}] is inactive; refusing to seed its grants.");
            }

            $permissionKeys = $assignment['permissions'];
            $permissionIds = $permissionKeys === null
                ? $allActivePermissionIds
                : array_values(array_unique(array_map(
                    static fn (string $key): int => (int) $permissionRows[$key]->permission_id,
                    $permissionKeys
                )));

            DB::table('role_permissions')->where('role_id', $role->role_id)->delete();

            if ($permissionIds !== []) {
                $rows = array_map(static fn (int $permissionId): array => [
                    'role_id' => $role->role_id,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $permissionIds);

                DB::table('role_permissions')->insert($rows);
            }
        }
    }
}
