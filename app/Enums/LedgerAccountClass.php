<?php

namespace App\Enums;

enum LedgerAccountClass: string
{
    case ASSET = 'ASSET';
    case LIABILITY = 'LIABILITY';
    case EQUITY = 'EQUITY';
    case REVENUE = 'REVENUE';
    case EXPENSE = 'EXPENSE';

    public function label(): string
    {
        return match ($this) {
            self::ASSET => 'أصول',
            self::LIABILITY => 'التزامات',
            self::EQUITY => 'حقوق ملكية',
            self::REVENUE => 'إيرادات',
            self::EXPENSE => 'مصروفات',
        };
    }
}