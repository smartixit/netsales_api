<?php

namespace App\Enums;

enum ValueType: string
{
    case TEXT = 'TEXT';
    case INTEGER = 'INTEGER';
    case BOOLEAN = 'BOOLEAN';
    case FLOAT = 'FLOAT';
    case JSON = 'JSON';
    case DATETIME = 'DATETIME';

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
            self::FLOAT => 'رقم عشري',
            self::JSON => 'بيانات JSON',
            self::DATETIME => 'تاريخ ووقت',
        };
    }

    public static function labels(): array
    {
        return [
            self::TEXT->value => self::TEXT->label(),
            self::INTEGER->value => self::INTEGER->label(),
            self::BOOLEAN->value => self::BOOLEAN->label(),
            self::FLOAT->value => self::FLOAT->label(),
            self::JSON->value => self::JSON->label(),
            self::DATETIME->value => self::DATETIME->label(),
        ];
    }
}
