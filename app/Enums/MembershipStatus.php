<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case INVITED = 'INVITED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'نشط',
            self::SUSPENDED => 'مجمّد',
            self::INVITED => 'مدعو',
        };
    }
}
