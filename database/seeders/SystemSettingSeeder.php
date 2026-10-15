<?php

namespace Database\Seeders;

use App\Interfaces\SystemSettingRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemSettingSeeder extends Seeder
{
    protected const FCM_ENDPOINT_TEMPLATE = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    protected const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    protected const TOKEN_CACHE_KEY = 'fcm_v1_access_token';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // ═══════════════════════════════════════════════════════════════
            // 1. GENERAL (إعدادات عامة وهوية النظام)
            // ═══════════════════════════════════════════════════════════════
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

            // ═══════════════════════════════════════════════════════════════
            // 2. FINANCIAL (السياسات المالية والعملات)
            // ═══════════════════════════════════════════════════════════════
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
                'setting_desc' => 'رمز العملة الأساسية في المطبوعات والفواتير',
            ],
            [
                'setting_category' => 'FINANCIAL',
                'setting_key' => 'low_balance_alert_threshold',
                'setting_value_type' => 'DECIMAL',
                'setting_value' => '5000.00',
                'setting_desc' => 'الحد الأدنى لرصيد محفظة نقطة البيع لإطلاق التنبيهات الدورية',
            ],
            [
                'setting_category' => 'FINANCIAL',
                'setting_key' => 'min_wallet_transfer_amount',
                'setting_value_type' => 'DECIMAL',
                'setting_value' => '100.00',
                'setting_desc' => 'الحد الأدنى لعملية تحويل أو شحن الرصيد',
            ],
            [
                'setting_category' => 'FINANCIAL',
                'setting_key' => 'max_wallet_transfer_amount',
                'setting_value_type' => 'DECIMAL',
                'setting_value' => '500000.00',
                'setting_desc' => 'الحد الأقصى للعملية الواحدة في تحويل الرصيد',
            ],

            // ═══════════════════════════════════════════════════════════════
            // 3. SECURITY (الأمان والمصادقة وإدارة الجلسات)
            // ═══════════════════════════════════════════════════════════════
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
                'setting_desc' => 'مدة صلاحية الجلسة بالدقائق قبل تسجيل الخروج التلقائي',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'otp_expiry_seconds',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '180',
                'setting_desc' => 'مدة صلاحية رمز التحقق المؤقت OTP بالثواني',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'otp_length',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '6',
                'setting_desc' => 'عدد خانات رمز التحقق المؤقت OTP',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'otp_cooldown_seconds',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '60',
                'setting_desc' => 'فترة التهدئة بالثواني بين طلبات توليد OTP المتتالية',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'otp_max_attempts',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '3',
                'setting_desc' => 'أقصى عدد لمحاولات إدخال رمز التحقق OTP الخاطئة قبل إبطاله',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'max_daily_reset_password_requests',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '1',
                'setting_desc' => 'أقصى عدد مسموح يومياً لطلبات استعادة كلمة المرور لكل مستخدم',
            ],
            [
                'setting_category' => 'SECURITY',
                'setting_key' => 'max_daily_2fa_requests',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '3',
                'setting_desc' => 'أقصى عدد مسموح يومياً لرموز التحقق بخطوتين 2FA لكل مستخدم',
            ],

            // ═══════════════════════════════════════════════════════════════
            // 4. SUBSCRIPTION (سياسات الاشتراكات والفترات التجريبية)
            // ═══════════════════════════════════════════════════════════════
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
                'setting_desc' => 'فترة السماح بالأيام بعد انتهاء الاشتراك قبل إيقاف الشبكة',
            ],
            [
                'setting_category' => 'SUBSCRIPTION',
                'setting_key' => 'auto_suspend_expired',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '1',
                'setting_desc' => 'إيقاف خدمات الشبكة تلقائياً بعد استنفاد فترة السماح',
            ],
            [
                'setting_category' => 'SUBSCRIPTION',
                'setting_key' => 'subscription_expiry_alert_days',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '3',
                'setting_desc' => 'عدد الأيام المسبقة لتنبيه الشبكة بقرب انتهاء الاشتراك',
            ],

            // ═══════════════════════════════════════════════════════════════
            // 5. NOTIFICATION (بوابة الرسائل SMSGate، الفايربيس FCM، والبريد)
            // ═══════════════════════════════════════════════════════════════
            // -- SMSGate Configurations --
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'sms_enabled',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '1',
                'setting_desc' => 'المفتاح السيادي العام لتفعيل أو تعطيل خدمة الرسائل النصية SMS',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_server_type',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'cloud',
                'setting_desc' => 'نوع خادم بوابة الرسائل (cloud, local, private)',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_base_url',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'https://api.sms-gate.app',
                'setting_desc' => 'رابط خادم بوابة الرسائل الأساسي',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_auth_type',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'basic',
                'setting_desc' => 'نوع المصادقة لبوابة الرسائل (basic, bearer)',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_api_token',
                'setting_value_type' => 'TEXT',
                'setting_value' => '',
                'setting_desc' => 'رمز توكن الـ API لبوابة الرسائل',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_refresh_token',
                'setting_value_type' => 'TEXT',
                'setting_value' => '',
                'setting_desc' => 'رمز توكن التحديث لبوابة الرسائل',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_username',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'K6YCIX',
                'setting_desc' => 'اسم المستخدم لحساب بوابة الرسائل',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_password',
                'setting_value_type' => 'TEXT',
                'setting_value' => '-2qulf1_hyfcyh',
                'setting_desc' => 'كلمة مرور بوابة الرسائل',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_device_id',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'JG6e0vibLZD9YIMLuTQcG',
                'setting_desc' => 'معرّف الجهاز المرسل في تطبيق SMSGate',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_sim_number',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '1',
                'setting_desc' => 'رقم الشريحة (SIM Slot) المستخدمة في الإرسال (1 أو 2)',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_skip_phone_validation',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '0',
                'setting_desc' => 'تخطي التحقق من صحة الرقم من قبل خادم البوابة',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'smsgate_device_active_within',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '0',
                'setting_desc' => 'التحقق من نشاط جهاز الإرسال خلال (بالساعات، 0 = معطل)',
            ],
            // -- Firebase Cloud Messaging (FCM HTTP v1) --
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'firebase_enabled',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '1',
                'setting_desc' => 'تفعيل أو تعطيل إرسال إشعارات الدفع الفورية (FCM Push Notifications)',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'firebase_project_id',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'netsales-1e503',
                'setting_desc' => 'معرّف مشروع Firebase في Google Cloud Console',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'firebase_credentials_path',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'storage/app/firebase-service-account.json',
                'setting_desc' => 'مسار ملف شهادة حساب الخدمة JSON الخاص بفايربيس',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'firebase_endpoint_template',
                'setting_value_type' => 'TEXT',
                'setting_value' => self::FCM_ENDPOINT_TEMPLATE,
                'setting_desc' => 'قالب رابط نقطة نهاية إرسال رسائل فايربيس FCM HTTP v1',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'firebase_scope',
                'setting_value_type' => 'TEXT',
                'setting_value' => self::FCM_SCOPE,
                'setting_desc' => 'نطاق مصادقة Google OAuth لرسائل فايربيس',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'firebase_token_cache_key',
                'setting_value_type' => 'TEXT',
                'setting_value' => self::TOKEN_CACHE_KEY,
                'setting_desc' => 'مفتاح كاش توكن الوصول لفايربيس في ذاكرة التخزين المؤقت',
            ],
            // -- Email Notification (SMTP Configurations) --
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'email_enabled',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '1',
                'setting_desc' => 'المفتاح السيادي لتفعيل أو تعطيل إرسال رسائل البريد الإلكتروني',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'mail_mailer',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'smtp',
                'setting_desc' => 'بروتوكول إرسال البريد الإلكتروني (smtp, sendmail, log)',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'mail_host',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'smtp.gmail.com',
                'setting_desc' => 'خادم البريد الإلكتروني SMTP Host',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'mail_port',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '465',
                'setting_desc' => 'منفذ خادم البريد الإلكتروني SMTP Port (465 أو 587)',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'mail_username',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'smartixitco@gmail.com',
                'setting_desc' => 'اسم المستخدم لحساب خادم البريد الإلكتروني SMTP',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'mail_password',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'gopajjyfzkzfaxgo',
                'setting_desc' => 'كلمة المرور أو App Password لحساب خادم البريد الإلكتروني',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'mail_encryption',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'ssl',
                'setting_desc' => 'نوع التشفير للبريد الإلكتروني (ssl أو tls)',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'mail_from_address',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'smartixitco@gmail.com',
                'setting_desc' => 'عنوان البريد الإلكتروني للمرسل',
            ],
            [
                'setting_category' => 'NOTIFICATION',
                'setting_key' => 'mail_from_name',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'NetSales | نظام مبيعات الشبكة',
                'setting_desc' => 'الاسم المعروض كمرسل للبريد الإلكتروني',
            ],

            // ═══════════════════════════════════════════════════════════════
            // 6. BACKUP (النسخ الاحتياطي التلقائي وجوجل درايف)
            // ═══════════════════════════════════════════════════════════════
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'backup.enabled',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '1',
                'setting_desc' => 'تفعيل مهمة النسخ الاحتياطي التلقائي الشامل للنظام',
            ],
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'backup.schedule_time',
                'setting_value_type' => 'TEXT',
                'setting_value' => '03:00',
                'setting_desc' => 'وقت تشغيل النسخ الاحتياطي التلقائي يومياً (بتوقيت صنعاء)',
            ],
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'backup.retention_count',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '30',
                'setting_desc' => 'عدد النسخ الاحتياطية المحتفظ بها قبل الحذف الدوري للأقدم',
            ],
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'backup.include_storage',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '0',
                'setting_desc' => 'تضمين مجلد التخزين العام مع ملفات النسخة الاحتياطية',
            ],
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'backup.max_archive_mb',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '500',
                'setting_desc' => 'الحد الأقصى لحجم ملف النسخة الاحتياطية بالميجابايت',
            ],
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'google_drive_client_id',
                'setting_value_type' => 'TEXT',
                'setting_value' => '',
                'setting_desc' => 'معرّف العميل Google OAuth Client ID لحساب Google Drive',
            ],
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'google_drive_client_secret',
                'setting_value_type' => 'TEXT',
                'setting_value' => '',
                'setting_desc' => 'المفتاح السري Google OAuth Client Secret لحساب Google Drive',
            ],
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'google_drive_folder_name',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'NetSales_Backups',
                'setting_desc' => 'اسم المجلد المخصص لحفظ النسخ على Google Drive',
            ],
            [
                'setting_category' => 'BACKUP',
                'setting_key' => 'google_drive_redirect_uri',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'http://localhost',
                'setting_desc' => 'رابط إعادة التوجيه لتوثيق Google OAuth',
            ],

            // ═══════════════════════════════════════════════════════════════
            // 7. SYSTEM (صيانة النظام، السجلات، ورفع الملفات)
            // ═══════════════════════════════════════════════════════════════
            [
                'setting_category' => 'SYSTEM',
                'setting_key' => 'system_logs_retention_days',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '60',
                'setting_desc' => 'مدة الاحتفاظ بسجلات أحداث النظام بالأيام قبل الأرشفة والتنظيف',
            ],
            [
                'setting_category' => 'SYSTEM',
                'setting_key' => 'maintenance_mode',
                'setting_value_type' => 'BOOLEAN',
                'setting_value' => '0',
                'setting_desc' => 'تفعيل وضع الصيانة العام للنظام مع قصر الوصول على المشرفين',
            ],
            [
                'setting_category' => 'SYSTEM',
                'setting_key' => 'max_file_upload_size_mb',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '10',
                'setting_desc' => 'الحد الأقصى لحجم الملف أو الصورة المرفوعة بالميجابايت',
            ],
            [
                'setting_category' => 'SYSTEM',
                'setting_key' => 'allowed_image_extensions',
                'setting_value_type' => 'TEXT',
                'setting_value' => 'jpg,jpeg,png,webp',
                'setting_desc' => 'امتدادات الصور المسموح برفعها في النظام (مفصولة بفواصل)',
            ],
            [
                'setting_category' => 'SYSTEM',
                'setting_key' => 'signed_url_lifetime_minutes',
                'setting_value_type' => 'INTEGER',
                'setting_value' => '30',
                'setting_desc' => 'مدة صلاحية الروابط الموقعة رقمياً بالدقائق للملفات المحمية',
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

        // تفريغ الكاش فور الانتهاء من التغذية لضمان قراءة التحديثات فورياً
        Cache::forget(SystemSettingRepositoryInterface::class);
        Cache::forget('system_settings_cache_all');
    }
}
