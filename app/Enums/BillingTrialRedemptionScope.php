<?php

namespace App\Enums;

enum BillingTrialRedemptionScope: string
{
    case NETWORK = 'NETWORK';
    case BILLING_ACCOUNT = 'BILLING_ACCOUNT';
    case PRODUCT = 'PRODUCT';

    public function label(): string
    {
        return match ($this) {
            self::NETWORK => 'مرة واحدة للشبكة',
            self::BILLING_ACCOUNT => 'مرة واحدة لحساب الفوترة',
            self::PRODUCT => 'مرة واحدة للمنتج لكل شبكة',
        };
    }
}
