<?php

namespace App\Enums;

enum RequestStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function value(): array
    {
        return self::values();
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::APPROVED => 'تمت الموافقة',
            self::REJECTED => 'تم الرفض',
            self::CANCELLED => 'تم الإلغاء',
        };
    }

    public static function labels(): array
    {
        return [
            self::PENDING->value => self::PENDING->label(),
            self::APPROVED->value => self::APPROVED->label(),
            self::REJECTED->value => self::REJECTED->label(),
            self::CANCELLED->value => self::CANCELLED->label(),
        ];
    }
}
