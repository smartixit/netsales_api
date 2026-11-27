<?php

namespace App\Enums;

enum UserSessionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function isRevoked(): bool
    {
        return $this === self::REVOKED;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'نشطة',
            self::EXPIRED => 'منتهية',
            self::REVOKED => 'ملغاة',
        };
    }

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::EXPIRED->value => self::EXPIRED->label(),
            self::REVOKED->value => self::REVOKED->label(),
        ];
    }
}
