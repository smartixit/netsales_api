<?php

namespace App\Services\Billing;

use App\Enums\LedgerAccountClass;
use App\Models\{Currency, LedgerAccount};

class BillingLedgerChartService
{
    private const ACCOUNTS = [
        ['code' => '1100', 'name' => 'الذمم المدينة - اشتراكات', 'class' => 'ASSET', 'normal' => 'DEBIT'],
        ['code' => '2100', 'name' => 'الإيرادات المؤجلة - اشتراكات', 'class' => 'LIABILITY', 'normal' => 'CREDIT'],
        ['code' => '4100', 'name' => 'إيرادات الاشتراكات', 'class' => 'REVENUE', 'normal' => 'CREDIT'],
        ['code' => '4110', 'name' => 'إيرادات الاستخدام الإضافي', 'class' => 'REVENUE', 'normal' => 'CREDIT'],
        ['code' => '4190', 'name' => 'تكلفة واستردادات الاشتراكات', 'class' => 'EXPENSE', 'normal' => 'DEBIT'],
    ];

    /** @return array<string, LedgerAccount> */
    public function ensureForCurrency(Currency $currency): array
    {
        $accounts = [];

        foreach (self::ACCOUNTS as $definition) {
            $account = LedgerAccount::query()->firstOrCreate(
                [
                    'account_code' => $definition['code'],
                    'currency_id' => $currency->currency_id,
                ],
                [
                    'name' => $definition['name'],
                    'account_class' => LedgerAccountClass::from($definition['class']),
                    'normal_balance' => $definition['normal'],
                    'is_control_account' => true,
                    'is_active' => true,
                ]
            );

            $accounts[$definition['code']] = $account;
        }

        return $accounts;
    }

    public function createTreasuryLedgerAccount(Currency $currency, string $reference, string $name): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            [
                'account_code' => '1000-'.$reference,
                'currency_id' => $currency->currency_id,
            ],
            [
                'name' => $name,
                'account_class' => LedgerAccountClass::ASSET,
                'normal_balance' => 'DEBIT',
                'is_control_account' => false,
                'is_active' => true,
            ]
        );
    }
}
