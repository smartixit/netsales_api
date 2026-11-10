<?php

namespace App\Enums;

enum BillingInvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case OPEN = 'OPEN';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PARTIALLY_CREDITED = 'PARTIALLY_CREDITED';
    case PAID = 'PAID';
    case CREDITED = 'CREDITED';
    case UNCOLLECTIBLE = 'UNCOLLECTIBLE';
    case VOID = 'VOID';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::OPEN => 'مستحقة',
            self::PARTIALLY_PAID => 'مدفوعة جزئياً',
            self::PARTIALLY_CREDITED => 'مخفضة بإشعار دائن',
            self::PAID => 'مدفوعة',
            self::CREDITED => 'مغطاة بإشعار دائن',
            self::UNCOLLECTIBLE => 'متعذر تحصيلها',
            self::VOID => 'ملغاة',
        };
    }
}
