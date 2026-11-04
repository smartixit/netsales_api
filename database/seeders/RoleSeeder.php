<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'role_key' => RoleKey::SUPER_ADMIN->value,
                'role_name' => RoleKey::SUPER_ADMIN->label(),
                'role_desc' => 'كامل الصلاحيات الفنية والإدارية والمالية على كافة وحدات النظام',
                'scope' => RoleScope::PLATFORM->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::SUPPORT_AGENT->value,
                'role_name' => RoleKey::SUPPORT_AGENT->label(),
                'role_desc' => 'متابعة تذاكر الدعم والشكاوى والتواصل مع أصحاب الشبكات والوكلاء',
                'scope' => RoleScope::PLATFORM->value,
                'is_system' => false,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::ACCOUNTANT->value,
                'role_name' => RoleKey::ACCOUNTANT->label(),
                'role_desc' => 'إدارة ومتابعة العمليات المالية العامة ومطابقة الحسابات على مستوى المنصة',
                'scope' => RoleScope::PLATFORM->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::NETWORK_OWNER->value,
                'role_name' => RoleKey::NETWORK_OWNER->label(),
                'role_desc' => 'إدارة شبكته الخاصة ونقاط البيع وباقات الكروت والمحافظ ومبيعاتها',
                'scope' => RoleScope::NETWORK->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::NETWORK_USER->value,
                'role_name' => RoleKey::NETWORK_USER->label(),
                'role_desc' => 'موظف تشغيلي في الشبكة لمتابعة باقات الكروت والطلبات والعمليات',
                'scope' => RoleScope::NETWORK->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::NETWORK_ACCOUNTANT->value,
                'role_name' => RoleKey::NETWORK_ACCOUNTANT->label(),
                'role_desc' => 'محاسب مالي متخصص لتدقيق ومتابعة حسابات وفواتير ومصروفات ومبيعات الشبكة',
                'scope' => RoleScope::NETWORK->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::POS_AGENT->value,
                'role_name' => RoleKey::POS_AGENT->label(),
                'role_desc' => 'إجراء عمليات البيع المباشر للمستخدمين، طلب الكروت، وإدارة رصيد النقطة والديون',
                'scope' => RoleScope::POS->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::POS_USER->value,
                'role_name' => RoleKey::POS_USER->label(),
                'role_desc' => 'كاشير وموظف مبيعات في نقطة البيع لإصدار الفواتير ومتابعة المخزون',
                'scope' => RoleScope::POS->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::POS_ACCOUNTANT->value,
                'role_name' => RoleKey::POS_ACCOUNTANT->label(),
                'role_desc' => 'محاسب مالي لنقطة البيع لمتابعة السجلات المالية والمبيعات والمصروفات والديون',
                'scope' => RoleScope::POS->value,
                'is_system' => true,
                'status' => true,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_key' => $role['role_key']],
                array_merge($role, [
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ])
            );
        }
    }
}
