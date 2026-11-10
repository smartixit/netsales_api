<?php

namespace App\Enums;

enum BillingTrialEligibilityMode: string
{
    case ANY = 'ANY';
    case NEW_NETWORK = 'NEW_NETWORK';
    case NEW_BILLING_ACCOUNT = 'NEW_BILLING_ACCOUNT';

    public function label(): string
    {
        return match ($this) {
            self::ANY => 'جميع العملاء',
            self::NEW_NETWORK => 'الشبكات التي لم تشترك من قبل',
            self::NEW_BILLING_ACCOUNT => 'حسابات الفوترة الجديدة فقط',
        };
    }
}
