<?php

namespace App\Enums;

enum BillingTrialStartTrigger: string
{
    case PURCHASE = 'PURCHASE';
    case FIRST_ACTIVATION = 'FIRST_ACTIVATION';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'عند شراء الاشتراك',
            self::FIRST_ACTIVATION => 'عند أول استخدام فعلي للخدمة',
        };
    }
}
