<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;

/**
 * Configures the launch payment methods for the Yemen/YER market.
 *
 * Every method is offline and subject to platform review. A gateway is kept
 * explicitly disabled until a real provider integration is deployed.
 */
class BillingPaymentMethodSeeder extends Seeder
{
    private const METHODS = [
        ['method_key' => 'CASH', 'name' => 'نقداً', 'method_type' => 'OFFLINE', 'requires_proof' => false, 'is_active' => true],
        ['method_key' => 'BANK_TRANSFER', 'name' => 'تحويل بنكي', 'method_type' => 'OFFLINE', 'requires_proof' => true, 'is_active' => true],
        ['method_key' => 'E_WALLET', 'name' => 'محفظة إلكترونية', 'method_type' => 'OFFLINE', 'requires_proof' => true, 'is_active' => true],
        ['method_key' => 'PAYMENT_GATEWAY', 'name' => 'بوابة دفع إلكترونية', 'method_type' => 'GATEWAY', 'requires_proof' => false, 'is_active' => false],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now('Asia/Aden');

            foreach (self::METHODS as $method) {
                DB::table('billing_payment_methods')->updateOrInsert(
                    ['method_key' => $method['method_key']],
                    [...$method, 'updated_at' => $now]
                );
            }

            $yerId = DB::table('currencies')
                ->where('code', 'YER')
                ->where('is_active', true)
                ->value('currency_id');

            if ($yerId === null) {
                throw new \\RuntimeException('YER must be seeded and active before billing payment methods.');
            }

            $methodIds = DB::table('billing_payment_methods')
                ->pluck('payment_method_id', 'method_key');

            foreach (self::METHODS as $method) {
                $methodId = (int) $methodIds[$method['method_key']];

                // Deactivate every non-launch currency mapping, including stale rows.
                DB::table('billing_payment_method_currencies')
                    ->where('payment_method_id', $methodId)
                    ->where('currency_id', '<>', $yerId)
                    ->update(['is_active' => false, 'updated_at' => $now]);

                DB::table('billing_payment_method_currencies')->updateOrInsert(
                    ['payment_method_id' => $methodId, 'currency_id' => $yerId],
                    ['is_active' => $method['is_active'], 'updated_at' => $now]
                );
            }
        }, 3);
    }
}
