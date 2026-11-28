<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;

/**
 * Seeds the supported currency reference set and the launch-market activation.
 *
 * All four currencies remain configured reference data. YER alone is active
 * for the initial Yemen launch; adding another market requires an explicit
 * product/finance decision rather than an incidental seeder rerun.
 */
class CurrencySeeder extends Seeder
{
    private const CURRENCIES = [
        ['code' => 'YER', 'numeric_code' => '886', 'name' => 'Yemeni Rial', 'native_name' => 'الريال اليمني', 'symbol' => 'ر.ي', 'decimal_places' => 2],
        ['code' => 'SAR', 'numeric_code' => '682', 'name' => 'Saudi Riyal', 'native_name' => 'الريال السعودي', 'symbol' => 'ر.س', 'decimal_places' => 2],
        ['code' => 'USD', 'numeric_code' => '840', 'name' => 'US Dollar', 'native_name' => 'الدولار الأمريكي', 'symbol' => '$', 'decimal_places' => 2],
        ['code' => 'AED', 'numeric_code' => '784', 'name' => 'UAE Dirham', 'native_name' => 'الدرهم الإماراتي', 'symbol' => 'د.إ', 'decimal_places' => 2],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now('Asia/Aden');

            foreach (self::CURRENCIES as $currency) {
                DB::table('currencies')->updateOrInsert(
                    ['code' => $currency['code']],
                    [
                        ...$currency,
                        'is_active' => $currency['code'] === 'YER',
                        'updated_at' => $now,
                    ]
                );
            }
        }, 3);
    }
}
