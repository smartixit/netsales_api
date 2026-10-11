<?php

namespace App\Enums;

enum BankAccountType: string
{
    case CURRENT = 'CURRENT';
    case SAVING = 'SAVING';
    case WALLET = 'WALLET';

    public static function value(): array
    {
        return array_column(self::cases(), 'value');

    }

    public function label(): string
    {
        return match ($this) {
            self::CURRENT => 'حساب جاري',
            self::SAVING => 'حساب توفير',
            self::WALLET => 'محفظة',
        };
    }

    public static function labels(): array
    {
        return [
            self::CURRENT->value => self::CURRENT->label(),
            self::SAVING->value => self::SAVING->label(),
            self::WALLET->value => self::WALLET->label(),
        ];
    }
}
