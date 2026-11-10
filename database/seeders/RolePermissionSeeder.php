<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
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
            // 1. مدير النظام العام (Full Platform SaaS & Administrative Authority)
            RoleKey::SUPER_ADMIN->value => $allPermissions->pluck('permission_id')->all(),

            // 2. الدعم الفني للمنصة (Platform Ticketing & Support)
            RoleKey::SUPPORT_AGENT->value => $allPermissions->filter(function ($perm) {
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

            // 3. محاسب المنصة العام (Platform Financial Controller)
            RoleKey::ACCOUNTANT->value => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'networks.view',
                    'networks.report',
                    'networks.export',
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'pos.view',
                    'pos.report',
                    'pos.export',
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
                    'billing.view',
                    'billing.approve',
                    'billing.report',
                    'bank_accounts.view',
                    'bank_accounts.manage',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            // 4. مزود الشبكة (Network Tenant Master Admin)
            RoleKey::NETWORK_OWNER->value => $allPermissions->filter(function ($perm) {
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
                    'pos_wallets.edit',
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
                    'requests.delete',
                    'requests.approve',
                    'requests.reject',
                    'requests.report',
                    'sales.view',
                    'sales.create',
                    'sales.edit',
                    'sales.delete',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.create',
                    'expenses.edit',
                    'expenses.delete',
                    'expenses.approve',
                    'expenses.report',
                    'expenses.export',
                    'support.view',
                    'support.create',
                    'support.edit',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            // 5. محاسب الشبكة (Network Financial Auditor & Bookkeeper)
            RoleKey::NETWORK_ACCOUNTANT->value => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'networks.view',
                    'network_wallets.view',
                    'network_wallets.report',
                    'network_wallets.export',
                    'pos.view',
                    'pos.report',
                    'pos.export',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'voucher_packages.export',
                    'requests.view',
                    'requests.report',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'expenses.view',
                    'expenses.create',
                    'expenses.edit',
                    'expenses.report',
                    'expenses.export',
                    'support.view',
                    'support.create',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            // 6. مستخدم الشبكة (Network Staff / Operational Operator)
            RoleKey::NETWORK_USER->value => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'networks.view',
                    'pos.view',
                    'voucher_packages.view',
                    'voucher_packages.export',
                    'requests.view',
                    'requests.create',
                    'requests.edit',
                    'requests.approve',
                    'requests.reject',
                    'requests.report',
                    'sales.view',
                    'sales.report',
                    'support.view',
                    'support.create',
                    'support.edit',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            // 7. وكيل نقطة البيع (POS Store Master Agent / Owner)
            RoleKey::POS_AGENT->value => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'pos.view',
                    'pos.edit',
                    'networks.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'requests.view',
                    'requests.create',
                    'requests.edit',
                    'requests.delete',
                    'requests.report',
                    'sales.view',
                    'sales.create',
                    'sales.edit',
                    'sales.delete',
                    'sales.report',
                    'sales.export',
                    'support.view',
                    'support.create',
                    'support.edit',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            // 8. محاسب نقطة البيع (POS Financial Auditor & Bookkeeper)
            RoleKey::POS_ACCOUNTANT->value => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'pos.view',
                    'networks.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'pos_wallets.export',
                    'voucher_packages.view',
                    'requests.view',
                    'requests.report',
                    'sales.view',
                    'sales.report',
                    'sales.export',
                    'support.view',
                    'support.create',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),

            // 9. مستخدم نقطة البيع (POS Clerk / Cashier)
            RoleKey::POS_USER->value => $allPermissions->filter(function ($perm) {
                return in_array($perm->permission_key, [
                    'pos.view',
                    'networks.view',
                    'pos_wallets.view',
                    'pos_wallets.report',
                    'voucher_packages.view',
                    'requests.view',
                    'requests.create',
                    'sales.view',
                    'sales.create',
                    'support.view',
                    'support.create',
                    'support.edit',
                    'notifications.view',
                ]);
            })->pluck('permission_id')->all(),
        ];

        foreach ($roleAssignments as $roleKey => $permissionIds) {
            $roleId = $roles[$roleKey] ?? null;
            if (! $roleId) {
                continue;
            }

            // مزامنة دقيقة لصلاحيات الدور
            DB::table('role_permissions')->where('role_id', $roleId)->delete();

            $insertData = array_map(fn ($permId) => [
                'role_id' => $roleId,
                'permission_id' => $permId,
                'created_at' => now('Asia/Aden'),
                'updated_at' => now('Asia/Aden'),
            ], $permissionIds);

            if (! empty($insertData)) {
                DB::table('role_permissions')->insert($insertData);
            }
        }
    }
}
