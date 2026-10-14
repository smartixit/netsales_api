<?php

namespace App\Enums;

enum NotificationType: string
{
    case SYSTEM = 'SYSTEM';
    case INFO = 'INFO';
    case WARNING = 'WARNING';

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
            self::SYSTEM => 'نظام',
            self::INFO => 'معلومة',
            self::WARNING => 'تحذير',
        };
    }

    public static function labels(): array
    {
        return [
            self::SYSTEM->value => self::SYSTEM->label(),
            self::INFO->value => self::INFO->label(),
            self::WARNING->value => self::WARNING->label(),
        ];
    }
}
