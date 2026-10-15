<?php

namespace App\Enums;

enum BackupStatus: string
{
    case QUEUED = 'QUEUED';

    case RUNNING = 'RUNNING';

    case SUCCESS = 'SUCCESS';

    case FAILED = 'FAILED';

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
            self::QUEUED => 'قيد الانتظار',
            self::RUNNING => 'قيد التنفيذ',
            self::SUCCESS => 'تم بنجاح',
            self::FAILED => 'فشل',
        };
    }

    public static function labels(): array
    {
        return [
            self::QUEUED->value => self::QUEUED->label(),
            self::RUNNING->value => self::RUNNING->label(),
            self::SUCCESS->value => self::SUCCESS->label(),
            self::FAILED->value => self::FAILED->label(),
        ];
    }
}
