<?php

namespace App\Enums;

enum BillingTrialDurationUnit: string
{
    case HOUR = 'HOUR';
    case DAY = 'DAY';
    case WEEK = 'WEEK';
    case MONTH = 'MONTH';

    public function label(): string
    {
        return match ($this) {
            self::HOUR => 'ساعة',
            self::DAY => 'يوم',
            self::WEEK => 'أسبوع',
            self::MONTH => 'شهر',
        };
    }
}
