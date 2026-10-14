<?php

namespace App\Enums;

enum ProviderSubscriptionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case QUEUED = 'QUEUED';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
    case SUSPENDED = 'SUSPENDED';

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

    public function isQueued(): bool
    {
        return $this === self::QUEUED;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'مفعل',
            self::QUEUED => 'في الانتظار',
            self::EXPIRED => 'منتهي',
            self::CANCELLED => 'ملغي',
            self::SUSPENDED => 'متوقف',
        };
    }

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::QUEUED->value => self::QUEUED->label(),
            self::EXPIRED->value => self::EXPIRED->label(),
            self::CANCELLED->value => self::CANCELLED->label(),
            self::SUSPENDED->value => self::SUSPENDED->label(),
        ];
    }
}
