<?php

namespace App\Enums;

enum UserDeviceStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case PENDING = 'PENDING';
    case BANNED = 'BANNED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
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
