<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = [
            [
                'action_type_key' => 'view',
                'action_type_name' => 'عرض واستعراض',
                'status' => true,
            ],
            [
                'action_type_key' => 'create',
                'action_type_name' => 'إضافة وإنشاء',
                'status' => true,
            ],
            [
                'action_type_key' => 'edit',
                'action_type_name' => 'تعديل وتحديث',
                'status' => true,
            ],
            [
                'action_type_key' => 'delete',
                'action_type_name' => 'حذف وتعطيل',
                'status' => true,
            ],
            [
                'action_type_key' => 'report',
                'action_type_name' => 'استعراض التقارير',
                'status' => true,
            ],
            [
                'action_type_key' => 'export',
                'action_type_name' => 'تصدير البيانات',
                'status' => true,
            ],
            [
                'action_type_key' => 'approve',
                'action_type_name' => 'اعتماد وموافقة',
                'status' => true,
            ],
            [
                'action_type_key' => 'reject',
                'action_type_name' => 'رفض وإلغاء',
                'status' => true,
            ],
            [
                'action_type_key' => 'manage',
                'action_type_name' => 'إدارة وتحكم متقدم',
                'status' => true,
            ],
        ];

        foreach ($actions as $action) {
            DB::table('action_types')->updateOrInsert(
                ['action_type_key' => $action['action_type_key']],
                array_merge($action, [
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ])
            );
        }
    }
}
