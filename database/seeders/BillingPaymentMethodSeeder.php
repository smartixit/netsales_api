<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['method_key' => 'CASH', 'name' => 'نقداً', 'method_type' => 'OFFLINE', 'requires_proof' => false, 'is_active' => true],
            ['method_key' => 'BANK_TRANSFER', 'name' => 'تحويل بنكي', 'method_type' => 'OFFLINE', 'requires_proof' => true, 'is_active' => true],
            ['method_key' => 'E_WALLET', 'name' => 'محفظة إلكترونية', 'method_type' => 'OFFLINE', 'requires_proof' => true, 'is_active' => true],
            ['method_key' => 'PAYMENT_GATEWAY', 'name' => 'بوابة دفع إلكترونية', 'method_type' => 'GATEWAY', 'requires_proof' => false, 'is_active' => false],
        ];

        foreach ($methods as $method) {
            DB::table('billing_payment_methods')->updateOrInsert(
                ['method_key' => $method['method_key']],
                [...$method, 'updated_at' => now('Asia/Aden'), 'created_at' => now('Asia/Aden')]
            );
        }

        $methodIds = DB::table('billing_payment_methods')->pluck('payment_method_id', 'method_key');
        $currencyIds = DB::table('currencies')->where('is_active', true)->pluck('currency_id');

        foreach ($methodIds as $methodId) {
            foreach ($currencyIds as $currencyId) {
                DB::table('billing_payment_method_currencies')->updateOrInsert(
                    ['payment_method_id' => $methodId, 'currency_id' => $currencyId],
                    ['is_active' => true, 'updated_at' => now('Asia/Aden'), 'created_at' => now('Asia/Aden')]
                );
            }
        }
    }
}
