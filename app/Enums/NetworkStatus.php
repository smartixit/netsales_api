<?php

namespace App\Enums;

enum NetworkStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case PENDING = 'PENDING';
    case REJECTED = 'REJECTED';
    case BANNED = 'BANNED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function value(): array
    {
        return self::values();
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isBanned(): bool
    {
        return $this === self::BANNED;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'مفعّل',
            self::INACTIVE => 'غير مفعّل',
            self::PENDING => 'قيد الانتظار',
            self::REJECTED => 'تم الرفض',
            self::BANNED => 'محظور',
        };
    }

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::PENDING->value => self::PENDING->label(),
            self::INACTIVE->value => self::INACTIVE->label(),
            self::BANNED->value => self::BANNED->label(),
            self::REJECTED->value => self::REJECTED->label(),
        ];
    }
}
