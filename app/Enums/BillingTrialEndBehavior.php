<?php

namespace App\Enums;

enum BillingTrialEndBehavior: string
{
    case CREATE_INVOICE = 'CREATE_INVOICE';
    case EXPIRE = 'EXPIRE';

    public function label(): string
    {
        return match ($this) {
            self::CREATE_INVOICE => 'إصدار فاتورة وتحويل الاشتراك إلى مدفوع',
            self::EXPIRE => 'إنهاء الاشتراك دون إصدار فاتورة',
        };
    }
}
