<?php

namespace App\Enums;

enum BillingInterval: string
{
    case DAY = 'DAY';
    case WEEK = 'WEEK';
    case MONTH = 'MONTH';
    case YEAR = 'YEAR';
    case ONE_TIME = 'ONE_TIME';

    public function label(): string
    {
        return match ($this) {
            self::DAY => 'يومي',
            self::WEEK => 'أسبوعي',
            self::MONTH => 'شهري',
            self::YEAR => 'سنوي',
            self::ONE_TIME => 'مرة واحدة',
        };
    }
}