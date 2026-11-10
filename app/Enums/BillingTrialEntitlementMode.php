<?php

namespace App\Enums;

enum BillingTrialEntitlementMode: string
{
    case FULL_PLAN = 'FULL_PLAN';
    case RESTRICTED = 'RESTRICTED';
    case NONE = 'NONE';

    public function label(): string
    {
        return match ($this) {
            self::FULL_PLAN => 'جميع مزايا الخطة',
            self::RESTRICTED => 'مزايا محددة للتجربة',
            self::NONE => 'دون إتاحة مزايا تشغيلية',
        };
    }
}
