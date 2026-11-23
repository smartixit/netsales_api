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
                'role_desc' => 'إدارة منصة NetSales وإعداداتها وخدماتها العامة دون ممارسة أدوار المستأجرين التشغيلية',
                'scope' => RoleScope::PLATFORM->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::SUPPORT_AGENT->value,
                'role_name' => RoleKey::SUPPORT_AGENT->label(),
                'role_desc' => 'معالجة تذاكر الدعم الفني ومراسلاتها دون صلاحيات إدارة المنصة أو تغيير بيانات المستأجرين',
                'scope' => RoleScope::PLATFORM->value,
                'is_system' => false,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::ACCOUNTANT->value,
                'role_name' => RoleKey::ACCOUNTANT->label(),
                'role_desc' => 'الرقابة المالية العامة للمنصة وقراءة المؤشرات المالية والتسويات وتنفيذ مهام الاعتماد المالي المحددة',
                'scope' => RoleScope::PLATFORM->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::NETWORK_OWNER->value,
                'role_name' => RoleKey::NETWORK_OWNER->label(),
                'role_desc' => 'إدارة شبكة واحدة أو أكثر وعضوياتها وروابط نقاط البيع وباقاتها وخصوماتها وطلبات التوريد ومحافظها وفواتيرها ومصروفاتها',
                'scope' => RoleScope::NETWORK->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::NETWORK_USER->value,
                'role_name' => RoleKey::NETWORK_USER->label(),
                'role_desc' => 'موظف تشغيلي ضمن شبكة محددة لمراجعة طلبات التوريد ومتابعة الباقات والفواتير والبيانات المصرح بها دون إدارة عضويات الشبكة أو محافظها',
                'scope' => RoleScope::NETWORK->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::NETWORK_ACCOUNTANT->value,
                'role_name' => RoleKey::NETWORK_ACCOUNTANT->label(),
                'role_desc' => 'محاسب ضمن شبكة محددة لمراجعة أرصدة المحافظ وكشوفها وفواتير المبيعات والطلبات والمصروفات وتقاريرها للقراءة والتصدير فقط',
                'scope' => RoleScope::NETWORK->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::POS_AGENT->value,
                'role_name' => RoleKey::POS_AGENT->label(),
                'role_desc' => 'مدير نقطة بيع يدير بياناتها وعضويات موظفيها وطلبات التوريد ومحفظتها وتقاريرها دون إصدار فواتير الشبكة',
                'scope' => RoleScope::POS->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::POS_USER->value,
                'role_name' => RoleKey::POS_USER->label(),
                'role_desc' => 'موظف نقطة بيع ينشئ طلبات التوريد ويتابع حالتها وباقات الشبكات المرتبطة دون إدارة النقطة أو المحفظة أو إصدار فواتير الشبكة',
                'scope' => RoleScope::POS->value,
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => RoleKey::POS_ACCOUNTANT->value,
                'role_name' => RoleKey::POS_ACCOUNTANT->label(),
                'role_desc' => 'محاسب ضمن نقطة بيع لمراجعة محفظتها وطلبات التوريد وفواتير الشراء وكشوفها وتقاريرها للقراءة والتصدير فقط',
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
