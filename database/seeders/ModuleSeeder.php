<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'auth' => ['المصادقة والأمان', 'heroicon-o-lock-closed', 'إدارة الجلسات والأجهزة والحظر الأمني'],
            'users' => ['المستخدمون', 'heroicon-o-users', 'إدارة حسابات مستخدمي المنصة'],
            'roles' => ['الأدوار والصلاحيات', 'heroicon-o-shield-check', 'عرض الأدوار وإسناد الصلاحيات المباشرة'],
            'geography' => ['الدليل الجغرافي', 'heroicon-o-map', 'إدارة المحافظات والمديريات'],
            'networks' => ['الشبكات', 'heroicon-o-wifi', 'إدارة واعتماد شبكات مزودي الخدمة'],
            'network_pos' => ['روابط الشبكات ونقاط البيع', 'heroicon-o-link', 'إدارة الارتباطات التجارية ودعوات الربط الآمن'],
            'network_wallets' => ['محافظ الشبكات', 'heroicon-o-wallet', 'إدارة محفظة الشبكة والإيداعات والسحوبات'],
            'pos' => ['نقاط البيع', 'heroicon-o-computer-desktop', 'إدارة واعتماد نقاط البيع'],
            'pos_wallets' => ['محافظ نقاط البيع', 'heroicon-o-banknotes', 'إدارة المحافظ والتحويلات والسقوف الائتمانية'],
            'voucher_packages' => ['باقات الشبكة', 'heroicon-o-ticket', 'إدارة الباقات والتعرفة الأساسية'],
            'package_discounts' => ['الخصومات التعاقدية', 'heroicon-o-tag', 'إدارة خصومات الباقات الخاصة بكل نقطة بيع'],
            'requests' => ['طلبات التوريد', 'heroicon-o-inbox-arrow-down', 'إنشاء ومراجعة واعتماد طلبات التوريد'],
            'sales' => ['فواتير المبيعات', 'heroicon-o-receipt-percent', 'إصدار ومراجعة وإدارة فواتير الشبكة'],
            'expenses' => ['المصروفات التشغيلية', 'heroicon-o-arrow-trending-down', 'تسجيل ومراجعة مصروفات الشبكات'],
            'billing' => ['فوترة المنصة', 'heroicon-o-credit-card', 'إدارة الحسابات والاشتراكات والفواتير والخزينة'],
            'support_tickets' => ['تذاكر الدعم الفني', 'heroicon-o-chat-bubble-left-right', 'إدارة تذاكر الدعم ومراسلاتها'],
            'notifications' => ['الإشعارات الإدارية', 'heroicon-o-bell', 'إدارة الإشعارات الإدارية الموجهة'],
            'system_logs' => ['سجلات النظام', 'heroicon-o-document-magnifying-glass', 'عرض سجلات التدقيق والأحداث'],
            'settings' => ['إعدادات النظام', 'heroicon-o-cog-6-tooth', 'إدارة إعدادات وتهيئة النظام'],
            'backups' => ['النسخ الاحتياطي', 'heroicon-o-cloud-arrow-up', 'إدارة النسخ الاحتياطي والتخزين السحابي'],
            'sms_settings' => ['إعدادات الرسائل', 'heroicon-o-chat-bubble-left-right', 'إدارة بوابات وإعدادات الرسائل النصية'],
            'scheduled_tasks' => ['المهام المجدولة', 'heroicon-o-clock', 'مراقبة تشغيل المهام المجدولة والاستدراك'],
        ];

        foreach ($modules as $key => [$name, $icon, $description]) {
            DB::table('modules')->updateOrInsert(
                ['module_key' => $key],
                [
                    'module_name' => $name,
                    'module_icon' => $icon,
                    'module_desc' => $description,
                    'status' => true,
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ]
            );
        }

        DB::table('modules')
            ->whereIn('module_key', ['support', 'bank_accounts'])
            ->update([
                'status' => false,
                'updated_at' => now('Asia/Aden'),
            ]);
    }
}
