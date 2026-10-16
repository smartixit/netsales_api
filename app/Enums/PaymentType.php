<?php

namespace App\Enums;

enum PaymentType: string
{
    case CASH = 'CASH';
    case BANK = 'BANK';
    case WALLET = 'WALLET';

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
            self::CASH => 'نقدي',
            self::BANK => 'بنكي',
            self::WALLET => 'محفظة',
        };
    }

    public static function labels(): array
    {
        return [
            self::CASH->value => self::CASH->label(),
            self::BANK->value => self::BANK->label(),
            self::WALLET->value => self::WALLET->label(),
        ];
    }
}
