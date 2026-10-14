<?php

namespace App\Enums;

enum WalletOperationType: string
{
    case DEPOSIT = 'DEPOSIT';
    case WITHDRAW = 'WITHDRAW';

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
            self::DEPOSIT => 'إيداع',
            self::WITHDRAW => 'سحب',
        };
    }

    public static function labels(): array
    {
        return [
            self::DEPOSIT->value => self::DEPOSIT->label(),
            self::WITHDRAW->value => self::WITHDRAW->label(),
        ];
    }
}
