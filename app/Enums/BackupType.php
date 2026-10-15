<?php

namespace App\Enums;

enum BackupType: string
{
    case AUTO = 'AUTO';
    case MANUAL = 'MANUAL';

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
            self::AUTO => 'تلقائي',
            self::MANUAL => 'يدوي',
        };
    }

    public static function labels(): array
    {
        return [
            self::AUTO->value => self::AUTO->label(),
            self::MANUAL->value => self::MANUAL->label(),
        ];
    }
}
