<?php

namespace App\Enums;

enum ProviderSubscriptionStatus: string {
    case ACTIVE = 'ACTIVE';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
    case SUSPENDED = 'SUSPENDED';

    public static function value(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'مفعل',
            self::EXPIRED => 'منتهي',
            self::CANCELLED => 'ملغي',
            self::SUSPENDED => 'متوقف',
        };
    }

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::EXPIRED->value => self::EXPIRED->label(),
            self::CANCELLED->value => self::CANCELLED->label(),
            self::SUSPENDED->value => self::SUSPENDED->label(),
        ];
    }
}