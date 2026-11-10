<?php

namespace App\Enums;

enum BillingPaymentStatus: string
{
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case VERIFIED = 'VERIFIED';
    case FAILED = 'FAILED';
    case REJECTED = 'REJECTED';
    case REFUNDED = 'REFUNDED';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'بانتظار المراجعة',
            self::VERIFIED => 'تم التحقق',
            self::FAILED => 'فاشل',
            self::REJECTED => 'مرفوض',
            self::REFUNDED => 'مسترد',
            self::PARTIALLY_REFUNDED => 'مسترد جزئياً',
        };
    }
}