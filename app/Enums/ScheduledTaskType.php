<?php

namespace App\Enums;

enum ScheduledTaskType: string
{
    case TASK_DAILY_SUMMARY = 'daily_summary';

    case TASK_MONTHLY_SUMMARY = 'monthly_summary';

    case TASK_SUBSCRIPTION_EXPIRY = 'subscription_expiry';

    case TASK_BACKUP_AUTO = 'backup_auto';

    case TASK_CLEANUP_EPHEMERAL = 'cleanup_ephemeral';

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
            self::TASK_DAILY_SUMMARY => 'الملخص المالي اليومي للشبكات (SMS)',
            self::TASK_MONTHLY_SUMMARY => 'الملخص المالي الشهري للشبكات (SMS)',
            self::TASK_SUBSCRIPTION_EXPIRY => 'تنبيه باقتراب انتهاء اشتراكات الشبكات',
            self::TASK_BACKUP_AUTO => 'النسخ الاحتياطي التلقائي الشامل للنظام (Google Drive)',
            self::TASK_CLEANUP_EPHEMERAL => 'تنظيف الجلسات المنتهية والمبطلة',
        };
    }

    public static function labels(): array
    {
        return [
            self::TASK_DAILY_SUMMARY->value => self::TASK_DAILY_SUMMARY->label(),
            self::TASK_MONTHLY_SUMMARY->value => self::TASK_MONTHLY_SUMMARY->label(),
            self::TASK_SUBSCRIPTION_EXPIRY->value => self::TASK_SUBSCRIPTION_EXPIRY->label(),
            self::TASK_BACKUP_AUTO->value => self::TASK_BACKUP_AUTO->label(),
            self::TASK_CLEANUP_EPHEMERAL->value => self::TASK_CLEANUP_EPHEMERAL->label(),
        ];
    }
}
