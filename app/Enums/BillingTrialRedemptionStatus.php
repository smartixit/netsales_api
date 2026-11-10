<?php

namespace App\Enums;

enum BillingTrialRedemptionStatus: string
{
    case RESERVED = 'RESERVED';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case ACTIVE = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case REVOKED = 'REVOKED';

    public function label(): string
    {
        return match ($this) {
            self::RESERVED => 'محجوزة بانتظار بدء التجربة',
            self::PENDING_APPROVAL => 'بانتظار موافقة المنصة',
            self::ACTIVE => 'تجربة قيد الاستخدام',
            self::COMPLETED => 'مكتملة',
            self::REVOKED => 'ملغاة',
        };
    }
}
