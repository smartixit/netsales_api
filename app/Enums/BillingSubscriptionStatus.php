<?php

namespace App\Enums;

enum BillingSubscriptionStatus: string
{
    case INCOMPLETE = 'INCOMPLETE';
    case TRIALING = 'TRIALING';
    case ACTIVE = 'ACTIVE';
    case PAST_DUE = 'PAST_DUE';
    case PAUSED = 'PAUSED';
    case SUSPENDED = 'SUSPENDED';
    case CANCELED = 'CANCELED';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::INCOMPLETE => 'غير مكتمل',
            self::TRIALING => 'تجريبي',
            self::ACTIVE => 'نشط',
            self::PAST_DUE => 'متأخر السداد',
            self::PAUSED => 'متوقف مؤقتاً',
            self::SUSPENDED => 'معلق',
            self::CANCELED => 'ملغي',
            self::EXPIRED => 'منتهي',
        };
    }
}