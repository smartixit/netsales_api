<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = DB::table('roles')->pluck('role_id', 'role_key');
        $allPermissions = DB::table('permissions')->get()->keyBy('permission_key');

        $roleAssignments = [
            'super_admin' => $allPermissions->pluck('permission_id')->all(),

            'system_admin' => $allPermissions->reject(function ($perm) {
                return in_array($perm->permission_key, [
                    'system_logs.delete',
                    'roles.delete',
                ]);
            })->pluck('permission_id')->all(),

            'network_owner' => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'networks.view',
                    'networks.edit',
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'pos.view',
                    'pos.create',
                    'pos.edit',
                    'pos.delete',
                    'pos.report',
                    'pos.export',
                    'pos_wallets.view',
                    'pos_wallets.create',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'pos_wallets.manage',
                    'voucher_packages.view',
                    'voucher_packages.create',
                    'voucher_packages.edit',
                    'voucher_packages.delete',
                    'voucher_packages.export',
                    'requests.view',
                    'requests.create',
                    'requests.edit',
                    'requests.approve',
                    'requests.reject',
                    'requests.report',
                    'sales.view',
                    'sales.create',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.create',
                    'expenses.edit',
                    'expenses.delete',
                    'expenses.report',
                    'expenses.export',
                    'subscriptions.view',
                    'subscriptions.report',
                    'bank_accounts.view',
                    'support.view',
                    'support.create',
                    'support.edit',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            'pos_agent' => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'pos.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'voucher_packages.view',
                    'requests.view',
                    'requests.create',
                    'sales.view',
                    'sales.create',
                    'sales.report',
                    'sales.export',
                    'support.view',
                    'support.create',
                    'support.edit',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            'accountant' => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.create',
                    'expenses.edit',
                    'expenses.delete',
                    'expenses.approve',
                    'expenses.report',
                    'expenses.export',
                    'subscriptions.view',
                    'subscriptions.approve',
                    'subscriptions.report',
                    'bank_accounts.view',
                    'bank_accounts.create',
                    'bank_accounts.edit',
                    'bank_accounts.manage',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            'support_agent' => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'users.view',
                    'networks.view',
                    'pos.view',
                    'support.view',
                    'support.create',
                    'support.edit',
                    'support.manage',
                    'notifications.view',
                    'notifications.create',
                ]);
            })->pluck('permission_id')->all(),
        ];

        foreach ($roleAssignments as $roleKey => $permissionIds) {
            $roleId = $roles[$roleKey] ?? null;
            if (! $roleId) {
                continue;
            }

            foreach ($permissionIds as $permId) {
                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => $roleId,
                        'permission_id' => $permId,
                    ],
                    [
                        'updated_at' => now('Asia/Aden'),
                        'created_at' => now('Asia/Aden'),
                    ]
                );
            }
        }
    }
}
