<?php

namespace App\Enums;

enum BanType: string
{
    case TEMPORARY = 'TEMPORARY';
    case PERMANENT = 'PERMANENT';

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
            self::TEMPORARY => 'مؤقت',
            self::PERMANENT => 'دائم',
        };
    }

    public static function labels(): array
    {
        return [
            self::TEMPORARY->value => self::TEMPORARY->label(),
            self::PERMANENT->value => self::PERMANENT->label(),
        ];
    }
}
