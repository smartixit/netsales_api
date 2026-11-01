<?php

namespace App\Enums;

enum RoleScope: string
{
    case PLATFORM = 'PLATFORM';
    case NETWORK = 'NETWORK';
    case POS = 'POS';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PLATFORM => 'نطاق المنصة العامة',
            self::NETWORK => 'نطاق مزود الشبكة',
            self::POS => 'نطاق نقطة البيع',
        };
    }
}
