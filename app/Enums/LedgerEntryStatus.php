<?php

namespace App\Enums;

enum LedgerEntryStatus: string
{
    case POSTED = 'POSTED';
    case REVERSED = 'REVERSED';

    public function label(): string
    {
        return match ($this) {
            self::POSTED => 'مرحّل',
            self::REVERSED => 'معكوس',
        };
    }
}