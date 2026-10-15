<?php

namespace App\Enums;

/**
 * حالات تنفيذ المهام المجدولة (Scheduled Task Statuses).
 */
enum ScheduledTaskStatus: string
{
    case SENT = 'sent';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';

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
            self::SENT => 'تم الإرسال',
            self::COMPLETED => 'مكتمل بنجاح',
            self::FAILED => 'فشل التنفيذ',
            self::SKIPPED => 'تم التجاوز',
        };
    }

    public function isSuccess(): bool
    {
        return in_array($this, [self::SENT, self::COMPLETED], true);
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isSkipped(): bool
    {
        return $this === self::SKIPPED;
    }
}
