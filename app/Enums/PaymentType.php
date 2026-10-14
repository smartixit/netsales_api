<?php

namespace App\Enums;

enum PaymentType: string
{
    case CASH = 'CASH';
    case CREDIT = 'CREDIT';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function value(): array
    {
        return self::values();
    }

    public function isCash(): bool
    {
        return $this === self::CASH;
    }

    public function isCredit(): bool
    {
        return $this === self::CREDIT;
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
