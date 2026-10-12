<?php

namespace App\Enums;

enum RequestStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public static function value(): array
    {
        return array_column(self::cases(), 'value');
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
