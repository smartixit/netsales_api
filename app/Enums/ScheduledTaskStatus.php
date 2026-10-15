<?php

namespace App\Enums;

enum ScheduledTaskStatus: string
{
    case STATUS_SENT = 'sent';

    case STATUS_FAILED = 'failed';

    case STATUS_PARTIALLY_SENT = 'partially_sent';

    case STATUS_COMPLETED = 'completed';

    case STATUS_SKIPPED = 'skipped';

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
            self::STATUS_SENT => 'تم الإرسال',
            self::STATUS_FAILED => 'فشل',
            self::STATUS_PARTIALLY_SENT => 'تم الإرسال جزئيا',
            self::STATUS_COMPLETED => 'تم الاكتمال',
            self::STATUS_SKIPPED => 'تم التجاهل',
        };
    }

    public static function labels(): array
    {
        return [
            self::STATUS_SENT->value => self::STATUS_SENT->label(),
            self::STATUS_FAILED->value => self::STATUS_FAILED->label(),
            self::STATUS_PARTIALLY_SENT->value => self::STATUS_PARTIALLY_SENT->label(),
            self::STATUS_COMPLETED->value => self::STATUS_COMPLETED->label(),
            self::STATUS_SKIPPED->value => self::STATUS_SKIPPED->label(),
        ];
    }
}
