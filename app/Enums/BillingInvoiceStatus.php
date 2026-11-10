<?php

namespace App\Enums;

enum BillingInvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case OPEN = 'OPEN';
    case PAID = 'PAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case UNCOLLECTIBLE = 'UNCOLLECTIBLE';
    case VOID = 'VOID';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::OPEN => 'مستحقة',
            self::PAID => 'مدفوعة',
            self::PARTIALLY_PAID => 'مدفوعة جزئياً',
            self::UNCOLLECTIBLE => 'متعذر تحصيلها',
            self::VOID => 'ملغاة',
        };
    }
}