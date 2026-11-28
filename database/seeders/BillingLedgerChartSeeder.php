<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Services\Billing\BillingLedgerChartService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Initializes the platform control chart for each launch-enabled currency.
 *
 * Treasury accounts are operational financial instruments and are never
 * fabricated by this reference-data seeder.
 */
class BillingLedgerChartSeeder extends Seeder
{
    public function run(BillingLedgerChartService $chart): void
    {
        DB::transaction(function () use ($chart): void {
            Currency::query()
                ->where('is_active', true)
                ->orderBy('currency_id')
                ->each(static function (Currency $currency) use ($chart): void {
                    $chart->ensureForCurrency($currency);
                });
        }, 3);
    }
}
