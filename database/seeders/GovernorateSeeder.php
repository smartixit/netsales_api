<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GovernorateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $governorates = [
            'أمانة العاصمة',
            'صنعاء',
            'عدن',
            'تعز',
            'حضرموت',
            'الحديدة',
            'إب',
            'ذمار',
            'مأرب',
            'شبوة',
            'المهرة',
            'لحج',
            'أبين',
            'الضالع',
            'عمران',
            'حجة',
            'صعدة',
            'البيضاء',
            'الجوف',
            'المحويت',
            'ريمة',
            'أرخبيل سقطرى',
        ];

        foreach ($governorates as $name) {
            DB::table('governorates')->updateOrInsert(
                ['governorate_name' => $name],
                [
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ]
            );
        }
    }
}
