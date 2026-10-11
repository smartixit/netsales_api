<?php

namespace App\Enums;

enum PaymentType: string
{
    case CASH = 'CASH';
    case CREDIT = 'CREDIT';

    public static function value(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'نقدي',
            self::CREDIT => 'آجل',
        };
    }

    public static function labels(): array
    {
        return [
            self::CASH->value => self::CASH->label(),
            self::CREDIT->value => self::CREDIT->label(),
        ];
    }
}
