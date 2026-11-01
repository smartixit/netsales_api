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
                'role_key' => 'SUPER_ADMIN',
                'role_name' => 'مدير النظام العام',
                'role_desc' => 'كامل الصلاحيات الفنية والإدارية والمالية على كافة وحدات النظام',
                'scope' => 'PLATFORM',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'SUPPORT_AGENT',
                'role_name' => 'دعم فني',
                'role_desc' => 'متابعة تذاكر الدعم والشكاوى والتواصل مع أصحاب الشبكات والوكلاء',
                'scope' => 'PLATFORM',
                'is_system' => false,
                'status' => true,
            ],
            [
                'role_key' => 'ACCOUNTANT',
                'role_name' => 'محاسب عام',
                'role_desc' => 'إدارة ومتابعة العمليات المالية العامة ومطابقة الحسابات',
                'scope' => 'PLATFORM',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'NETWORK_OWNER',
                'role_name' => 'مزود شبكة',
                'role_desc' => 'إدارة شبكته الخاصة ونقاط البيع وباقات الكروت والمحافظ ومبيعاتها',
                'scope' => 'NETWORK',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'NETWORK_USER',
                'role_name' => 'مستخدم شبكة',
                'role_desc' => 'يقوم ببيع الكروت فقط واستخدام محفظته الشخصية فقط',
                'scope' => 'NETWORK',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'NETWORK_ACCOUNTANT',
                'role_name' => 'محاسب شبكة',
                'role_desc' => 'إدارة فواتير ومصروفات وحركات محفظة الشبكة',
                'scope' => 'NETWORK',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'POS_AGENT',
                'role_name' => 'وكيل نقطة بيع',
                'role_desc' => 'إجراء عمليات البيع المباشر للمستخدمين، طلب الكروت، وإدارة رصيد النقطة',
                'scope' => 'POS',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'POS_USER',
                'role_name' => 'مستخدم نقطة بيع',
                'role_desc' => 'يقوم ببيع الكروت فقط واستخدام محفظته الشخصية فقط',
                'scope' => 'POS',
                'is_system' => true,
                'status' => true,
            ],
            [
                'role_key' => 'POS_ACCOUNTANT',
                'role_name' => 'محاسب نقطة بيع',
                'role_desc' => 'متابعة فواتير ومشتريات وحسابات نقطة البيع',
                'scope' => 'POS',
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
