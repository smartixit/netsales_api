<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'bank_name'           => 'بنك الكريمي للتمويل الأصغر الإسلامي',
                'bank_logo'           => 'banks/kuraimi.png',
                'account_holder_name' => 'شركة سمارتكس للحلول الذكية',
                'account_number'      => '120123456',
                'account_type'        => 'CURRENT',
                'account_currency'    => 'YER',
                'account_balance'     => 0.00,
                'status'              => true,
            ],
            [
                'bank_name'           => 'بنك الكريمي للتمويل الأصغر الإسلامي',
                'bank_logo'           => 'banks/kuraimi.png',
                'account_holder_name' => 'شركة سمارتكس للحلول الذكية',
                'account_number'      => '120123456',
                'account_type'        => 'CURRENT',
                'account_currency'    => 'SAR',
                'account_balance'     => 0.00,
                'status'              => true,
            ],
            [
                'bank_name'           => 'بنك التضامن الإسلامي',
                'bank_logo'           => 'banks/tadhamon.png',
                'account_holder_name' => 'شركة سمارتكس للحلول الذكية',
                'account_number'      => '210987654',
                'account_type'        => 'CURRENT',
                'account_currency'    => 'YER',
                'account_balance'     => 0.00,
                'status'              => true,
            ],
            [
                'bank_name'           => 'بنك القطيبي الإسلامي للتمويل الأصغر',
                'bank_logo'           => 'banks/qutaibi.png',
                'account_holder_name' => 'شركة سمارتكس للحلول الذكية',
                'account_number'      => '330554433',
                'account_type'        => 'SAVING',
                'account_currency'    => 'YER',
                'account_balance'     => 0.00,
                'status'              => true,
            ],
            [
                'bank_name'           => 'محفظة كاش الإلكترونية (سبأفون)',
                'bank_logo'           => 'banks/cash.png',
                'account_holder_name' => 'سمارتكس لنظم المعلومات',
                'account_number'      => '770000000',
                'account_type'        => 'WALLET',
                'account_currency'    => 'YER',
                'account_balance'     => 0.00,
                'status'              => true,
            ],
            [
                'bank_name'           => 'محفظة جيب الإلكترونية (مريسي)',
                'bank_logo'           => 'banks/jeeb.png',
                'account_holder_name' => 'سمارتكس لنظم المعلومات',
                'account_number'      => '771111111',
                'account_type'        => 'WALLET',
                'account_currency'    => 'YER',
                'account_balance'     => 0.00,
                'status'              => true,
            ],
        ];

        foreach ($accounts as $account) {
            DB::table('bank_accounts')->updateOrInsert(
                [
                    'account_number'   => $account['account_number'],
                    'account_currency' => $account['account_currency'],
                    'account_type'     => $account['account_type'],
                ],
                array_merge($account, [
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ])
            );
        }
    }
}
