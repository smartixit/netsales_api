<?php

namespace App\Enums;

enum BillingTrialAdjustmentType: string
{
    case EXTEND = 'EXTEND';
    case SHORTEN = 'SHORTEN';
    case END_EARLY = 'END_EARLY';
    case REVOKE = 'REVOKE';

    public function label(): string
    {
        return match ($this) {
            self::EXTEND => 'تمديد الفترة التجريبية',
            self::SHORTEN => 'تقليص الفترة التجريبية',
            self::END_EARLY => 'إنهاء التجربة مبكراً',
            self::REVOKE => 'إلغاء استحقاق التجربة',
        };
    }
}
