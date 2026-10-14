<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            [
                'module_key' => 'auth',
                'module_name' => 'المصادقة والجلسات',
                'module_icon' => 'heroicon-o-lock-closed',
                'module_desc' => 'إدارة تسجيل الدخول، أمان الجلسات، وعمليات التحقق الثنائي OTP',
                'status' => true,
            ],
            [
                'module_key' => 'users',
                'module_name' => 'المستخدمين',
                'module_icon' => 'heroicon-o-users',
                'module_desc' => 'إدارة حسابات المستخدمين، بياناتهم، وأجهزتهم المعتمدة',
                'status' => true,
            ],
            [
                'module_key' => 'roles',
                'module_name' => 'الأدوار والصلاحيات',
                'module_icon' => 'heroicon-o-shield-check',
                'module_desc' => 'إدارة أدوار النظام وتخصيص مصفوفة الصلاحيات',
                'status' => true,
            ],
            [
                'module_key' => 'geography',
                'module_name' => 'الدليل الجغرافي',
                'module_icon' => 'heroicon-o-map',
                'module_desc' => 'إدارة المحافظات والمديريات والمناطق الجغرافية',
                'status' => true,
            ],
            [
                'module_key' => 'networks',
                'module_name' => 'الشبكات والمزودين',
                'module_icon' => 'heroicon-o-wifi',
                'module_desc' => 'إدارة شبكات الإنترنت المسجلة والمزودين وحالات الاعتماد',
                'status' => true,
            ],
            [
                'module_key' => 'network_wallets',
                'module_name' => 'محافظ الشبكات',
                'module_icon' => 'heroicon-o-wallet',
                'module_desc' => 'إدارة الأرصدة المالية وحركات الشحن والخصم لشبكات التوزيع',
                'status' => true,
            ],
            [
                'module_key' => 'pos',
                'module_name' => 'نقاط البيع',
                'module_icon' => 'heroicon-o-computer-desktop',
                'module_desc' => 'إدارة نقاط البيع التابعة للشبكات والوكلاء ومواقعها',
                'status' => true,
            ],
            [
                'module_key' => 'pos_wallets',
                'module_name' => 'محافظ نقاط البيع',
                'module_icon' => 'heroicon-o-banknotes',
                'module_desc' => 'إدارة أرصدة نقاط البيع وسجلات المعاملات والتحويلات المالية',
                'status' => true,
            ],
            [
                'module_key' => 'voucher_packages',
                'module_name' => 'باقات وكروت الشبكة',
                'module_icon' => 'heroicon-o-ticket',
                'module_desc' => 'إدارة فئات الكروت، أسعارها، الخصومات، وباقات البيانات',
                'status' => true,
            ],
            [
                'module_key' => 'requests',
                'module_name' => 'طلبات التعبئة والكروت',
                'module_icon' => 'heroicon-o-inbox-arrow-down',
                'module_desc' => 'معالجة وتتبع طلبات الكروت والتعبئة بين النقاط والشبكات',
                'status' => true,
            ],
            [
                'module_key' => 'sales',
                'module_name' => 'المبيعات والفواتير',
                'module_icon' => 'heroicon-o-shopping-cart',
                'module_desc' => 'إصدار فواتير البيع ومتابعة الإيرادات والتحصيلات',
                'status' => true,
            ],
            [
                'module_key' => 'expenses',
                'module_name' => 'المصروفات والسندات',
                'module_icon' => 'heroicon-o-arrow-trending-down',
                'module_desc' => 'إدارة بنود المصروفات وسندات الصرف والمرفقات المالية',
                'status' => true,
            ],
            [
                'module_key' => 'subscriptions',
                'module_name' => 'خطط الاشتراكات',
                'module_icon' => 'heroicon-o-sparkles',
                'module_desc' => 'إدارة باقات اشتراك الشبكات، الميزات، وطلبات التجديد',
                'status' => true,
            ],
            [
                'module_key' => 'bank_accounts',
                'module_name' => 'الحسابات البنكية',
                'module_icon' => 'heroicon-o-building-library',
                'module_desc' => 'إدارة حسابات البنوك والمحافظ الإلكترونية للتحصيلات',
                'status' => true,
            ],
            [
                'module_key' => 'support',
                'module_name' => 'الدعم الفني',
                'module_icon' => 'heroicon-o-chat-bubble-left-right',
                'module_desc' => 'إدارة تذاكر الدعم الفني، الاستفسارات، والمحادثات',
                'status' => true,
            ],
            [
                'module_key' => 'notifications',
                'module_name' => 'الإشعارات والتنبيهات',
                'module_icon' => 'heroicon-o-bell',
                'module_desc' => 'إرسال الإشعارات التلقائية والرسائل التنبيهية للمستخدمين',
                'status' => true,
            ],
            [
                'module_key' => 'system_logs',
                'module_name' => 'سجلات النظام والأمان',
                'module_icon' => 'heroicon-o-document-magnifying-glass',
                'module_desc' => 'مراقبة سجلات الأحداث، الأمان، وتتبع العمليات الحساسة',
                'status' => true,
            ],
            [
                'module_key' => 'settings',
                'module_name' => 'إعدادات النظام',
                'module_icon' => 'heroicon-o-cog-6-tooth',
                'module_desc' => 'تخصيص الإعدادات العامة، المالية، الأمان، والرسائل',
                'status' => true,
            ],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['module_key' => $module['module_key']],
                array_merge($module, [
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ])
            );
        }
    }
}
