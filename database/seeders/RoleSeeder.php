<?php

namespace Database\Seeders;

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
                'role_key' => 'super_admin',
                'role_name' => 'مدير النظام العام',
                'role_desc' => 'كامل الصلاحيات الفنية والإدارية والمالية على كافة وحدات النظام',
                'is_system' => true,
                'status' => true,
            ],
            // [
            //     'role_key'   => 'system_admin',
            //     'role_name'  => 'مدير إداري للنظام',
            //     'role_desc'  => 'إدارة المستخدمين والشبكات والاشتراكات والموافقة على العمليات',
            //     'is_system'  => true,
            //     'status'     => true,
            // ],
            [
                'role_key' => 'network_owner',
                'role_name' => 'مالك / مزود شبكة',
                'role_desc' => 'إدارة شبكته الخاصة ونقاط البيع وباقات الكروت والمحافظ ومبيعاتها',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'pos_agent',
                'role_name' => 'وكيل نقطة بيع',
                'role_desc' => 'إجراء عمليات البيع المباشر للمستخدمين، طلب الكروت، وإدارة رصيد النقطة',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'accountant',
                'role_name' => 'المحاسب المالي',
                'role_desc' => 'إدارة ومتابعة العمليات المالية، المصروفات، فواتير المبيعات، ومطابقة الحسابات',
                'is_system' => false,
                'status' => true,
            ],
            [
                'role_key' => 'support_agent',
                'role_name' => 'أخصائي الدعم الفني',
                'role_desc' => 'متابعة تذاكر الدعم والشكاوى والتواصل مع أصحاب الشبكات والوكلاء',
                'is_system' => false,
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
