<?php

namespace App\Enums;

/**
 * أنواع المهام المجدولة في نظام NetSales، مع تعريف جدولها الزمني المركزي.
 */
enum ScheduledTaskType: string
{
    case OUTBOX_PROCESS = 'outbox_process';
    case BILLING_PROCESS_RENEWALS = 'billing_process_renewals';
    case BILLING_PROCESS_DUNNING = 'billing_process_dunning';
    case BILLING_RECOGNIZE_REVENUE = 'billing_recognize_revenue';
    case REPORTS_DAILY_SMS = 'reports_daily_sms';
    case REPORTS_MONTHLY_SMS = 'reports_monthly_sms';
    case ALERTS_LOW_BALANCE = 'alerts_low_balance';
    case BACKUP_AUTO = 'backup_auto';
    case CLEANUP_USER_SESSIONS = 'cleanup_user_sessions';
    case CLEANUP_EXPIRED_OTP = 'cleanup_expired_otp';
    case SECURITY_LIFT_BANS = 'security_lift_bans';
    case SYSTEM_PRUNE_LOGS = 'system_prune_logs';
    case REPORTS_PURGE_EXPIRED_ARTIFACTS = 'reports_purge_expired_artifacts';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function value(): array
    {
        return self::values();
    }

    public function label(): string
    {
        return match ($this) {
            self::OUTBOX_PROCESS => 'معالجة أحداث صندوق الإرسال اللحظي',
            self::BILLING_PROCESS_RENEWALS => 'معالجة تجديد الاشتراكات والفترات التجريبية المنتهية',
            self::BILLING_PROCESS_DUNNING => 'معالجة التذكيرات المالية وفترات السماح والتنبيهات',
            self::BILLING_RECOGNIZE_REVENUE => 'الاعتراف اليومي بالإيرادات المؤجلة',
            self::REPORTS_DAILY_SMS => 'إرسال الملخص المالي اليومي للشبكات (SMS)',
            self::REPORTS_MONTHLY_SMS => 'إرسال الملخص المالي الشهري للشبكات (SMS)',
            self::ALERTS_LOW_BALANCE => 'فحص وتنبيه انخفاض أرصدة محافظ نقاط البيع',
            self::BACKUP_AUTO => 'النسخ الاحتياطي التلقائي الشامل للنظام (Google Drive)',
            self::CLEANUP_USER_SESSIONS => 'تنظيف جلسات المستخدمين المنتهية والمبطلة',
            self::CLEANUP_EXPIRED_OTP => 'تنظيف رموز التحقق المؤقتة (OTP) المنتهية أو المستعملة',
            self::SECURITY_LIFT_BANS => 'رفع الحظر الأمني المنتهي تلقائياً عن المستخدمين والأجهزة',
            self::SYSTEM_PRUNE_LOGS => 'أرشفة وتنظيف سجلات النظام القديمة',
            self::REPORTS_PURGE_EXPIRED_ARTIFACTS => 'تنظيف ملفات التقارير المحمية المنتهية الصلاحية',
        };
    }

    public function command(): string
    {
        return match ($this) {
            self::OUTBOX_PROCESS => 'outbox:process',
            self::BILLING_PROCESS_RENEWALS => 'billing:process-renewals',
            self::BILLING_PROCESS_DUNNING => 'billing:process-dunning',
            self::BILLING_RECOGNIZE_REVENUE => 'billing:recognize-revenue',
            self::REPORTS_DAILY_SMS => 'reports:daily-summary-sms',
            self::REPORTS_MONTHLY_SMS => 'reports:monthly-summary-sms',
            self::ALERTS_LOW_BALANCE => 'alerts:low-balance',
            self::BACKUP_AUTO => 'backup:auto',
            self::CLEANUP_USER_SESSIONS => 'cleanup:user-sessions',
            self::CLEANUP_EXPIRED_OTP => 'cleanup:expired-otp',
            self::SECURITY_LIFT_BANS => 'security:lift-expired-bans',
            self::SYSTEM_PRUNE_LOGS => 'system:prune-logs',
            self::REPORTS_PURGE_EXPIRED_ARTIFACTS => 'reports:purge-expired-artifacts',
        };
    }

    /**
     * الجدولة الموحدة بتوقيت Asia/Aden.
     *
     * Supported kinds: every_minute, every_fifteen_minutes, daily_at,
     * twice_daily, weekly_on, and monthly_on.
     *
     * BACKUP_AUTO reads its exact time from BackupSettingsService.
     */
    public function schedule(): array
    {
        return match ($this) {
            self::OUTBOX_PROCESS => ['kind' => 'every_minute'],
            self::BILLING_PROCESS_RENEWALS,
            self::BILLING_PROCESS_DUNNING => ['kind' => 'every_fifteen_minutes'],
            self::BILLING_RECOGNIZE_REVENUE => ['kind' => 'daily_at', 'time' => '01:05'],
            self::REPORTS_DAILY_SMS => ['kind' => 'daily_at', 'time' => '20:00'],
            self::REPORTS_MONTHLY_SMS => ['kind' => 'monthly_on', 'day' => 1, 'time' => '08:00'],
            self::ALERTS_LOW_BALANCE => ['kind' => 'twice_daily', 'times' => ['09:00', '15:00']],
            self::BACKUP_AUTO => ['kind' => 'daily_at', 'time' => null],
            self::CLEANUP_USER_SESSIONS => ['kind' => 'daily_at', 'time' => '02:00'],
            self::CLEANUP_EXPIRED_OTP => ['kind' => 'daily_at', 'time' => '02:15'],
            self::SECURITY_LIFT_BANS => ['kind' => 'daily_at', 'time' => '02:30'],
            self::SYSTEM_PRUNE_LOGS => ['kind' => 'weekly_on', 'day' => 0, 'time' => '04:00'],
            self::REPORTS_PURGE_EXPIRED_ARTIFACTS => ['kind' => 'daily_at', 'time' => '02:45'],
        };
    }

    /**
     * Kept as a compatibility descriptor for existing callers and diagnostics.
     */
    public function frequency(): string
    {
        return match ($this->schedule()['kind']) {
            'every_minute' => 'every_minute',
            'every_fifteen_minutes' => 'every_fifteen_minutes',
            'weekly_on' => 'weekly',
            'monthly_on' => 'monthly',
            default => 'daily',
        };
    }

    public function defaultTargetHour(): int
    {
        $schedule = $this->schedule();
        $time = $schedule['time'] ?? ($schedule['times'][0] ?? null);

        if ($time === null) {
            return 3;
        }

        return (int) explode(':', $time)[0];
    }
}
