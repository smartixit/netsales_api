<?php

namespace App\Enums;

enum ValueType: string
{
    case TEXT = 'TEXT';
    case INTEGER = 'INTEGER';
    case BOOLEAN = 'BOOLEAN';

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
            self::TEXT => 'نص',
            self::INTEGER => 'رقم صحيح',
            self::BOOLEAN => 'قيمة منطقية',
        };
    }

    public static function labels(): array
    {
        return [
            self::TEXT->value => self::TEXT->label(),
            self::INTEGER->value => self::INTEGER->label(),
            self::BOOLEAN->value => self::BOOLEAN->label(),
        ];
    }
}
