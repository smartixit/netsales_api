<?php

namespace App\Enums;

/**
 * حالات سداد فواتير المبيعات (Invoice Payment Statuses).
 */
enum InvoicePaymentStatus: string
{
    case PAID = 'PAID';                     // مدفوعة بالكامل
    case PARTIALLY_PAID = 'PARTIALLY_PAID'; // مدفوعة جزئياً
    case UNPAID = 'UNPAID';                 // غير مدفوعة (آجلة بالكامل)

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function value(): array
    {
        return self::values();
    }

    public function label(): string
    {
        return match ($this) {
            self::PAID => 'مدفوعة بالكامل',
            self::PARTIALLY_PAID => 'مدفوعة جزئياً',
            self::UNPAID => 'غير مدفوعة (آجلة)',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isPartiallyPaid(): bool
    {
        return $this === self::PARTIALLY_PAID;
    }

    public function isUnpaid(): bool
    {
        return $this === self::UNPAID;
    }
}
