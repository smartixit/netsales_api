<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // GENERAL
            [
                'setting_category'   => 'GENERAL',
                'setting_key'        => 'app_name',
                'setting_value_type' => 'TEXT',
                'setting_value'      => 'NetSales | مبيعات الشبكة',
                'setting_desc'       => 'اسم المنظومة المعروض في الواجهات والتقارير',
            ],
            [
                'setting_category'   => 'GENERAL',
                'setting_key'        => 'app_company_name',
                'setting_value_type' => 'TEXT',
                'setting_value'      => 'Smartix IT Solutions',
                'setting_desc'       => 'اسم الشركة المطورة / المشغلة',
            ],
            [
                'setting_category'   => 'GENERAL',
                'setting_key'        => 'app_support_phone',
                'setting_value_type' => 'TEXT',
                'setting_value'      => '+967772957881',
                'setting_desc'       => 'رقم هاتف الدعم الفني وخدمة العملاء',
            ],
            [
                'setting_category'   => 'GENERAL',
                'setting_key'        => 'app_support_email',
                'setting_value_type' => 'TEXT',
                'setting_value'      => 'smartixitco@gmail.com',
                'setting_desc'       => 'البريد الإلكتروني للدعم الفني',
            ],
            [
                'setting_category'   => 'GENERAL',
                'setting_key'        => 'default_locale',
                'setting_value_type' => 'TEXT',
                'setting_value'      => 'ar',
                'setting_desc'       => 'اللغة الافتراضية للواجهة (ar / en)',
            ],
            [
                'setting_category'   => 'GENERAL',
                'setting_key'        => 'default_timezone',
                'setting_value_type' => 'TEXT',
                'setting_value'      => 'Asia/Aden',
                'setting_desc'       => 'المنطقة الزمنية المعتمدة للنظام',
            ],

            // FINANCIAL
            [
                'setting_category'   => 'FINANCIAL',
                'setting_key'        => 'base_currency',
                'setting_value_type' => 'TEXT',
                'setting_value'      => 'YER',
                'setting_desc'       => 'العملة الأساسية للعمليات والمعاملات',
            ],
            [
                'setting_category'   => 'FINANCIAL',
                'setting_key'        => 'currency_symbol',
                'setting_value_type' => 'TEXT',
                'setting_value'      => 'ر.ي',
                'setting_desc'       => 'رمز العملة الأساسية',
            ],

            // SECURITY
            [
                'setting_category'   => 'SECURITY',
                'setting_key'        => 'max_login_attempts',
                'setting_value_type' => 'INTEGER',
                'setting_value'      => '5',
                'setting_desc'       => 'أقصى عدد لمحاولات الدخول الخاطئة قبل الحظر المؤقت',
            ],
            [
                'setting_category'   => 'SECURITY',
                'setting_key'        => 'lockout_duration_minutes',
                'setting_value_type' => 'INTEGER',
                'setting_value'      => '15',
                'setting_desc'       => 'مدة حظر الحساب بالدقائق عند تجاوز محاولات الدخول',
            ],
            [
                'setting_category'   => 'SECURITY',
                'setting_key'        => 'session_lifetime_minutes',
                'setting_value_type' => 'INTEGER',
                'setting_value'      => '1440',
                'setting_desc'       => 'مدة صلاحية الجلسة قبل تسجيل الخروج التلقائي',
            ],
            [
                'setting_category'   => 'SECURITY',
                'setting_key'        => 'otp_expiry_seconds',
                'setting_value_type' => 'INTEGER',
                'setting_value'      => '180',
                'setting_desc'       => 'مدة صلاحية رمز التحقق المؤقت OTP بالثواني',
            ],

            // SUBSCRIPTION
            [
                'setting_category'   => 'SUBSCRIPTION',
                'setting_key'        => 'trial_period_days',
                'setting_value_type' => 'INTEGER',
                'setting_value'      => '14',
                'setting_desc'       => 'عدد أيام الفترة التجريبية المجانية للشبكات الجديدة',
            ],
            [
                'setting_category'   => 'SUBSCRIPTION',
                'setting_key'        => 'grace_period_days',
                'setting_value_type' => 'INTEGER',
                'setting_value'      => '3',
                'setting_desc'       => 'فترة السماح بالأيام بعد انتهاء الاشتراك قبل الإيقاف',
            ],
            [
                'setting_category'   => 'SUBSCRIPTION',
                'setting_key'        => 'auto_suspend_expired',
                'setting_value_type' => 'BOOLEAN',
                'setting_value'      => '1',
                'setting_desc'       => 'إيقاف خدمات الشبكة تلقائياً بعد استنفاد فترة السماح',
            ],

            // NOTIFICATION
            [
                'setting_category'   => 'NOTIFICATION',
                'setting_key'        => 'enable_sms_gateway',
                'setting_value_type' => 'BOOLEAN',
                'setting_value'      => '1',
                'setting_desc'       => 'تفعيل بوابة الرسائل القصيرة SMSGate للإشعارات وOTP',
            ],
            [
                'setting_category'   => 'NOTIFICATION',
                'setting_key'        => 'low_balance_threshold',
                'setting_value_type' => 'INTEGER',
                'setting_value'      => '5000',
                'setting_desc'       => 'حد التنبيه عند انخفاض رصيد محفظة نقطة البيع أو الشبكة',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $setting['setting_key']],
                array_merge($setting, [
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ])
            );
        }
    }
}
