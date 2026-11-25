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
     * Active API capabilities that deliberately have no assignable role.
     *
     * - Network/POS creation is self-registration; entity deletion and restore
     *   are not supported through operational memberships.
     * - Network-wallet treasury operations are reserved for PLATFORM root;
     *   operational roles cannot create, edit, deposit, or withdraw its balance.
     * - Wallet deletion/restoration is prohibited to preserve balances and
     *   ledger history.
     *
     * Keep these keys out of all explicit roles and the PLATFORM registry.
     * A permission added here remains a deliberate deny, never an omitted grant.
     *
     * @var list<string>
     */
    public const INTENTIONALLY_UNGRANTED_PERMISSION_KEYS = [
        'networks.delete',
        'networks.restore',
        'networks.force_delete',
        'network_wallets.delete',
        'network_wallets.restore',
        'network_wallets.force_delete',
        'pos.delete',
        'pos.restore',
        'pos.force_delete',
        'pos_wallets.delete',
        'pos_wallets.restore',
        'pos_wallets.force_delete',
    ];

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
                    'networks.create',
                    'networks.view',
                    'network_members.view',
                    'network_members.create',
                    'network_members.change_role',
                    'network_members.change_status',
                    'network_members.delete',
                    'networks.edit',
                    'networks.create_pos_link',
                    'networks.update_pos_link',
                    'networks.delete_pos_link',
                    'networks.toggle_pos_link',
                    'networks.create_link_challenge',
                    'networks.revoke_link_challenge',
                    'networks.view_pos_agreements',
                    'networks.manage_pos_agreements',
                    'networks.view_pos_link_events',
                    'networks.restore_pos_link',
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
                    'voucher_package_discounts.create',
                    'voucher_package_discounts.edit',
                    'voucher_package_discounts.toggle_status',
                    'voucher_package_discounts.delete',
                    'voucher_package_discounts.restore',
                    'voucher_package_discounts.force_delete',
                    'requests.view',
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
                    'support.edit',
                    'support.delete',
                    'support.restore',
                    'support.force_delete',
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
                    'networks.view_pos_agreements',
                    'networks.view_pos_link_events',
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
                    'pos.create',
                    'pos.view',
                    'pos_members.view',
                    'pos_members.create',
                    'pos_members.change_role',
                    'pos_members.change_status',
                    'pos_members.delete',
                    'pos.edit',
                    'pos.preview_link_challenge',
                    'pos.confirm_link_challenge',
                    'pos.view_network_agreements',
                    'pos.manage_network_agreements',
                    'pos.view_network_link_events',
                    'pos.restore_network_link',
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
                    'pos.view_network_agreements',
                    'pos.view_network_link_events',
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

        $activePermissionKeys = DB::table('permissions')
            ->where('status', true)
            ->whereNull('deleted_at')
            ->pluck('permission_key')
            ->all();

        $missingIntentionalDenials = array_values(array_diff(
            self::INTENTIONALLY_UNGRANTED_PERMISSION_KEYS,
            $activePermissionKeys
        ));
        if ($missingIntentionalDenials !== []) {
            throw new RuntimeException(
                'Role permission seeding is blocked; intentional deny keys are missing from the active catalog: '
                .implode(', ', $missingIntentionalDenials)
            );
        }

        $explicitlyGrantedDenials = array_values(array_intersect(
            self::INTENTIONALLY_UNGRANTED_PERMISSION_KEYS,
            $requestedPermissionKeys
        ));
        if ($explicitlyGrantedDenials !== []) {
            throw new RuntimeException(
                'Role permission seeding is blocked; intentionally denied keys were assigned to a role: '
                .implode(', ', $explicitlyGrantedDenials)
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

        $platformRegistry = app(PlatformPermissionRegistry::class);
        $platformRegistryDenials = array_values(array_filter(
            self::INTENTIONALLY_UNGRANTED_PERMISSION_KEYS,
            static fn (string $permissionKey): bool => $platformRegistry->allows($permissionKey)
        ));

        if ($platformRegistryDenials !== []) {
            throw new RuntimeException(
                'Role permission seeding is blocked; intentionally denied keys cannot be added to the PLATFORM registry: '
                .implode(', ', $platformRegistryDenials)
            );
        }

        // Treasury capabilities are supplied by the SUPER_ADMIN role itself;
        // never preserve direct grants that could delegate them to other roles.
        $superAdminOnlyPermissionIds = DB::table('permissions')
            ->whereIn('permission_key', PlatformPermissionRegistry::SUPER_ADMIN_ONLY_PERMISSION_KEYS)
            ->pluck('permission_id')
            ->all();

        if ($superAdminOnlyPermissionIds !== []) {
            DB::table('user_permissions')
                ->whereIn('permission_id', $superAdminOnlyPermissionIds)
                ->delete();
        }

        // Direct permission assignments are global: the pivot contains no
        // network_id or pos_id. Keep the remaining grants only on active PLATFORM users.
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
