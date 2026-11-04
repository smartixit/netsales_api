<?php

namespace App\Enums;

enum LinkingChallengeStatus: string
{
    case PENDING = 'PENDING';
    case CONSUMED = 'CONSUMED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';

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

    public function isConsumed(): bool
    {
        return $this === self::CONSUMED;
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
            self::PENDING => 'قيد الانتظار',
            self::CONSUMED => 'مكتمل',
            self::EXPIRED => 'منتهي الصلاحية',
            self::REVOKED => 'ملغي',
        };
    }

    public static function labels(): array
    {
        return [
            self::PENDING->value => self::PENDING->label(),
            self::CONSUMED->value => self::CONSUMED->label(),
            self::EXPIRED->value => self::EXPIRED->label(),
            self::REVOKED->value => self::REVOKED->label(),
        ];
    }
}
