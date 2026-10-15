<?php

namespace App\Enums;

/**
 * أنواع المهام المجدولة في نظام NetSales (Scheduled Task Types).
 */
enum ScheduledTaskType: string
{
    case SUBSCRIPTIONS_EXPIRE_OUTDATED = 'subscriptions_expire_outdated';
    case SUBSCRIPTIONS_NOTIFY_EXPIRING = 'subscriptions_notify_expiring';
    case REPORTS_DAILY_SMS = 'reports_daily_sms';
    case REPORTS_MONTHLY_SMS = 'reports_monthly_sms';
    case ALERTS_LOW_BALANCE = 'alerts_low_balance';
    case BACKUP_AUTO = 'backup_auto';
    case CLEANUP_USER_SESSIONS = 'cleanup_user_sessions';
    case CLEANUP_EXPIRED_OTP = 'cleanup_expired_otp';
    case SECURITY_LIFT_BANS = 'security_lift_bans';
    case SYSTEM_PRUNE_LOGS = 'system_prune_logs';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function value(): array
    {
        return self::values();
    }

    /**
     * الاسم الوصفي للمهمة باللغة العربية
     */
    public function label(): string
    {
        return match ($this) {
            self::SUBSCRIPTIONS_EXPIRE_OUTDATED => 'فحص وإنهاء اشتراكات الشبكات المنتهية',
            self::SUBSCRIPTIONS_NOTIFY_EXPIRING => 'تنبيه باقتراب موعد انتهاء اشتراكات الشبكات',
            self::REPORTS_DAILY_SMS => 'إرسال الملخص المالي اليومي للشبكات (SMS)',
            self::REPORTS_MONTHLY_SMS => 'إرسال الملخص المالي الشهري للشبكات (SMS)',
            self::ALERTS_LOW_BALANCE => 'فحص وتنبيه انخفاض أرصدة محافظ نقاط البيع',
            self::BACKUP_AUTO => 'النسخ الاحتياطي التلقائي الشامل للنظام (Google Drive)',
            self::CLEANUP_USER_SESSIONS => 'تنظيف جلسات المستخدمين المنتهية والمبطلة',
            self::CLEANUP_EXPIRED_OTP => 'تنظيف رموز التحقق المؤقتة (OTP) المنتهية أو المستعملة',
            self::SECURITY_LIFT_BANS => 'رفع الحظر الأمني المنتهي تلقائياً عن المستخدمين والأجهزة',
            self::SYSTEM_PRUNE_LOGS => 'أرشفة وتنظيف سجلات النظام القديمة',
        };
    }

    /**
     * أمر الكونسول (Artisan Command) المرتبط بالمهمة
     */
    public function command(): string
    {
        return match ($this) {
            self::SUBSCRIPTIONS_EXPIRE_OUTDATED => 'subscriptions:expire-outdated',
            self::SUBSCRIPTIONS_NOTIFY_EXPIRING => 'subscriptions:notify-expiring',
            self::REPORTS_DAILY_SMS => 'reports:daily-summary-sms',
            self::REPORTS_MONTHLY_SMS => 'reports:monthly-summary-sms',
            self::ALERTS_LOW_BALANCE => 'alerts:low-balance',
            self::BACKUP_AUTO => 'backup:auto',
            self::CLEANUP_USER_SESSIONS => 'cleanup:user-sessions',
            self::CLEANUP_EXPIRED_OTP => 'cleanup:expired-otp',
            self::SECURITY_LIFT_BANS => 'security:lift-expired-bans',
            self::SYSTEM_PRUNE_LOGS => 'system:prune-logs',
        };
    }

    /**
     * دورية تنفيذ المهمة (hourly, daily, weekly, monthly)
     */
    public function frequency(): string
    {
        return match ($this) {
            self::SUBSCRIPTIONS_EXPIRE_OUTDATED => 'hourly',
            self::SYSTEM_PRUNE_LOGS => 'weekly',
            self::REPORTS_MONTHLY_SMS => 'monthly',
            default => 'daily',
        };
    }

    /**
     * ساعة الاستحقاق الافتراضية
     */
    public function defaultTargetHour(): int
    {
        return match ($this) {
            self::SUBSCRIPTIONS_EXPIRE_OUTDATED => 0,
            self::SUBSCRIPTIONS_NOTIFY_EXPIRING => 10,
            self::REPORTS_DAILY_SMS, self::REPORTS_MONTHLY_SMS => 20,
            self::ALERTS_LOW_BALANCE => 12,
            self::BACKUP_AUTO => 3,
            self::CLEANUP_USER_SESSIONS, self::CLEANUP_EXPIRED_OTP, self::SECURITY_LIFT_BANS => 2,
            self::SYSTEM_PRUNE_LOGS => 4,
        };
    }
}
