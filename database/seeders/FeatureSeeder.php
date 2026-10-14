<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'feature_name' => 'max_pos_count',
                'feature_value_type' => 'INTEGER',
                'feature_value' => '5',
                'feature_desc' => 'الحد الأقصى لعدد نقاط البيع المسموح بربطها بالشبكة',
                'status' => true,
            ],
            [
                'feature_name' => 'max_voucher_packages',
                'feature_value_type' => 'INTEGER',
                'feature_value' => '10',
                'feature_desc' => 'الحد الأقصى لفئات وباقات الكروت التي يمكن إنشاؤها',
                'status' => true,
            ],
            [
                'feature_name' => 'max_monthly_sales_volume',
                'feature_value_type' => 'DECIMAL',
                'feature_value' => '500000.00',
                'feature_desc' => 'السقف الشهري الإجمالي لحجم المبيعات المنفذة (ر.ي)',
                'status' => true,
            ],
            [
                'feature_name' => 'advanced_analytics',
                'feature_value_type' => 'BOOLEAN',
                'feature_value' => '0',
                'feature_desc' => 'الوصول إلى لوحة التحليلات المتقدمة والتقارير الرسومية',
                'status' => true,
            ],
            [
                'feature_name' => 'sms_notifications',
                'feature_value_type' => 'BOOLEAN',
                'feature_value' => '0',
                'feature_desc' => 'إشعارات الرسائل النصية القصيرة عند كل عملية بيع وشحن',
                'status' => true,
            ],
            [
                'feature_name' => 'custom_voucher_template',
                'feature_value_type' => 'BOOLEAN',
                'feature_value' => '0',
                'feature_desc' => 'تخصيص قالب وشعار كروت الشبكة والطباعة الحرارية',
                'status' => true,
            ],
            [
                'feature_name' => 'multi_branch_support',
                'feature_value_type' => 'BOOLEAN',
                'feature_value' => '0',
                'feature_desc' => 'دعم تعدد فروع الشبكة والتحكم المركزي الموزع',
                'status' => true,
            ],
            [
                'feature_name' => 'api_integration',
                'feature_value_type' => 'BOOLEAN',
                'feature_value' => '0',
                'feature_desc' => 'الربط البرمجي عبر API مع أنظمة المايكروتك ومحطات التوزيع',
                'status' => true,
            ],
            [
                'feature_name' => 'priority_support',
                'feature_value_type' => 'BOOLEAN',
                'feature_value' => '0',
                'feature_desc' => 'دعم فني مخصص ذو أولوية على مدار الساعة',
                'status' => true,
            ],
            [
                'feature_name' => 'automated_cloud_backup',
                'feature_value_type' => 'BOOLEAN',
                'feature_value' => '0',
                'feature_desc' => 'نسخ احتياطي سحابي تلقائي للبيانات والعمليات اليومية',
                'status' => true,
            ],
        ];

        foreach ($features as $feature) {
            DB::table('features')->updateOrInsert(
                ['feature_name' => $feature['feature_name']],
                array_merge($feature, [
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ])
            );
        }
    }
}
