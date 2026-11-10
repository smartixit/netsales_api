<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'YER', 'numeric_code' => '886', 'name' => 'Yemeni Rial', 'native_name' => 'الريال اليمني', 'symbol' => 'ر.ي', 'decimal_places' => 2],
            ['code' => 'SAR', 'numeric_code' => '682', 'name' => 'Saudi Riyal', 'native_name' => 'الريال السعودي', 'symbol' => 'ر.س', 'decimal_places' => 2],
            ['code' => 'USD', 'numeric_code' => '840', 'name' => 'US Dollar', 'native_name' => 'الدولار الأمريكي', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'AED', 'numeric_code' => '784', 'name' => 'UAE Dirham', 'native_name' => 'الدرهم الإماراتي', 'symbol' => 'د.إ', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                [...$currency, 'is_active' => true, 'updated_at' => now('Asia/Aden'), 'created_at' => now('Asia/Aden')]
            );
        }
    }
}
