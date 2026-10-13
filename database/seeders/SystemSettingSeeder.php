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
                'setting_category' => 'GENERAL',
                'setting_key' => 'app_name',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'NetSales | مبيعات الشبكة',
                'setting_desc' => 'اسم المنظومة المعروض في الواجهات والتقارير',
            ],
            [
                'setting_category' => 'GENERAL',
                'setting_key' => 'app_company_name',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'Smartix IT Solutions',
                'setting_desc' => 'اسم الشركة المطورة / المشغلة',
            ],
            [
                'setting_category' => 'GENERAL',
                'setting_key' => 'app_support_phone',
                'setting_value_type' => 'TEXT',
                'setting_value' => '+967772957881',
                'setting_desc' => 'رقم هاتف الدعم الفني وخدمة العملاء',
            ],
            [
                'setting_category' => 'GENERAL',
                'setting_key' => 'app_support_email',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'smartixitco@gmail.com',
                'setting_desc' => 'البريد الإلكتروني للدعم الفني',
            ],
            [
                'setting_category' => 'GENERAL',
                'setting_key' => 'default_locale',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'ar',
                'setting_desc' => 'اللغة الافتراضية للواجهة (ar / en)',
            ],
            [
                'setting_category' => 'GENERAL',
                'setting_key' => 'default_timezone',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'Asia/Aden',
                'setting_desc' => 'المنطقة الزمنية المعتمدة للنظام',
            ],

            // FINANCIAL
            [
                'setting_category' => 'FINANCIAL',
                'setting_key' => 'base_currency',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'YER',
                'setting_desc' => 'العملة الأساسية للعمليات والمعاملات',
            ],
            [
                'setting_category' => 'FINANCIAL',
                'setting_key' => 'currency_symbol',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'ر.ي',
                'setting_desc' => 'رمز العملة الأساسية',
            ],

            // SECURITY
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'max_login_attempts',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '5',
                'setting_desc' => 'أقصى عدد لمحاولات الدخول الخاطئة قبل الحظر المؤقت',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'lockout_duration_minutes',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '15',
                'setting_desc' => 'مدة حظر الحساب بالدقائق عند تجاوز محاولات الدخول',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'session_lifetime_minutes',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '1440',
                'setting_desc' => 'مدة صلاحية الجلسة قبل تسجيل الخروج التلقائي',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'otp_expiry_seconds',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '180',
                'setting_desc' => 'مدة صلاحية رمز التحقق المؤقت OTP بالثواني',
            ],

            // SUBSCRIPTION
            [
                'setting_category' => 'SUBSCRIPTION',
                'setting_key' => 'trial_period_days',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '14',
                'setting_desc' => 'عدد أيام الفترة التجريبية المجانية للشبكات الجديدة',
            ],
            [
                'setting_category' => 'SUBSCRIPTION',
                'setting_key' => 'grace_period_days',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '3',
                'setting_desc' => 'فترة السماح بالأيام بعد انتهاء الاشتراك قبل الإيقاف',
            ],
            [
                'setting_category' => 'SUBSCRIPTION',
                'setting_key' => 'auto_suspend_expired',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '1',
                'setting_desc' => 'إيقاف خدمات الشبكة تلقائياً بعد استنفاد فترة السماح',
            ],

            // NOTIFICATION
            [
                'setting_key' => 'smsgate_server_type',
                'setting_value' => 'cloud',
                'setting_value_type' => 'TEXT',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'نوع خادم الرسائل (cloud, local, private)',
            ],
            [
                'setting_key' => 'smsgate_base_url',
                'setting_value' => 'https://api.sms-gate.app',
                'setting_value_type' => 'TEXT',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'رابط خادم الرسائل',

            ],
            [
                'setting_key' => 'smsgate_auth_type',
                'setting_value' => 'basic',
                'setting_value_type' => 'TEXT',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'نوع المصادقة (bearer, basic)',

            ],
            [
                'setting_key' => 'smsgate_api_token',
                'setting_value' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzbXMtZ2F0ZS5hcHAiLCJzdWIiOiJLNllDSVgiLCJleHAiOjE3ODI0MjUxMzcsImlhdCI6MTc4MjQyNDIzNywianRpIjoiemh1RER6Z2NHdjIySVRaLVdPY2lCIiwidXNlcl9pZCI6Iks2WUNJWCIsInNjb3BlcyI6WyJtZXNzYWdlczpzZW5kIiwibWVzc2FnZXM6cmVhZCIsIm1lc3NhZ2VzOmxpc3QiLCJkZXZpY2VzOmxpc3QiLCJzZXR0aW5nczpyZWFkIiwic2V0dGluZ3M6d3JpdGUiLCJ0b2tlbnM6bWFuYWdlIl19.bQj0BdZBPU7zAB7z9TEvgjthilv3HZqm28V0yMXOTQU',
                'setting_value_type' => 'TEXT',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'توكن API',

            ],
            [
                'setting_key' => 'smsgate_refresh_token',
                'setting_value' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzbXMtZ2F0ZS5hcHAiLCJzdWIiOiJLNllDSVgiLCJleHAiOjE3ODUwMTYyMzcsImlhdCI6MTc4MjQyNDIzNywianRpIjoiYVhPd1lDa0pkRWZXM2NDQUxrWE1pIiwidXNlcl9pZCI6Iks2WUNJWCIsInNjb3BlcyI6WyJ0b2tlbnM6cmVmcmVzaCJdLCJvcmlnaW5hbF9zY29wZXMiOlsibWVzc2FnZXM6c2VuZCIsIm1lc3NhZ2VzOnJlYWQiLCJtZXNzYWdlczpsaXN0IiwiZGV2aWNlczpsaXN0Iiwic2V0dGluZ3M6cmVhZCIsInNldHRpbmdzOndyaXRlIiwidG9rZW5zOm1hbmFnZSJdfQ.knJcCXa0FC4KhQyEPuaUrWV4ryZJgaW5Kk9pPIqXbrw',
                'setting_value_type' => 'TEXT',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'توكن التحديث',

            ],
            [
                'setting_key' => 'smsgate_token_expires_at',
                'setting_value' => '2026-06-25T22:05:37Z',
                'setting_value_type' => 'DATETIME',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'تاريخ انتهاء التوكن',
            ],
            [
                'setting_key' => 'smsgate_username',
                'setting_value' => 'K6YCIX',
                'setting_value_type' => 'TEXT',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'اسم المستخدم',
            ],
            [
                'setting_key' => 'smsgate_password',
                'setting_value' => '-2qulf1_hyfcyh',
                'setting_value_type' => 'TEXT',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'كلمة المرور',
            ],
            [
                'setting_key' => 'smsgate_device_id',
                'setting_value' => 'JG6e0vibLZD9YlMLuTQcG',
                'setting_value_type' => 'TEXT',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'معرف الجهاز',
            ],
            [
                'setting_key' => 'smsgate_sim_number',
                'setting_value' => '1',
                'setting_value_type' => 'INTEGER',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'رقم الشريحة',
            ],
            [
                'setting_key' => 'smsgate_skip_phone_validation',
                'setting_value' => 'false',
                'setting_value_type' => 'BOOLEAN',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'تخطي التحقق من الرقم',
            ],
            [
                'setting_key' => 'smsgate_device_active_within',
                'setting_value' => '0',
                'setting_value_type' => 'INTEGER',
                'setting_category' => 'NOTIFICATION',
                'setting_desc' => 'نشط خلال (بالساعات)',
            ],
            [
                'setting_key' => 'sms.enabled',
                'setting_value' => '1',
                'setting_value_type' => 'BOOLEAN',
                'setting_category' => 'notification',
                'setting_desc' => 'تفعيل خدمة الرسائل النصية (المفتاح العام السيّد؛ رموز OTP مستثنة)',
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
