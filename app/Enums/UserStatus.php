<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'ACTIVE';
    case PENDING = 'PENDING';
    case INACTIVE = 'INACTIVE';
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

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }

    public function isBanned(): bool
    {
        return $this === self::BANNED;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'مفعل',
            self::INACTIVE => 'غير مفعل',
            self::PENDING => 'قيد الانتظار',
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
        ];
    }
}
