<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUserId = DB::table('users')->orderBy('user_id')->value('user_id') ?? 1;
        $features = DB::table('features')->pluck('feature_id', 'feature_name');

        $plans = [
            [
                'plan_uuid' => 'plan-trial-free',
                'plan_name' => 'الباقة التجريبية (Trial)',
                'plan_price' => 0.00,
                'plan_discount' => 0.00,
                'plan_validity' => 14,
                'plan_desc' => 'فترة تجريبية مجانية لمدة 14 يوماً لاستكشاف كافة إمكانيات المنظومة.',
                'status' => true,
                'features' => [
                    'max_pos_count' => '2',
                    'max_voucher_packages' => '5',
                    'max_monthly_sales_volume' => '200000.00',
                    'advanced_analytics' => '0',
                    'sms_notifications' => '0',
                    'custom_voucher_template' => '0',
                    'multi_branch_support' => '0',
                    'api_integration' => '0',
                    'priority_support' => '0',
                    'automated_cloud_backup' => '0',
                ],
            ],
            [
                'plan_uuid' => 'plan-starter-tier',
                'plan_name' => 'باقة البداية (Starter)',
                'plan_price' => 12000.00,
                'plan_discount' => 0.00,
                'plan_validity' => 30,
                'plan_desc' => 'مثالية للشبكات الصغيرة والمزودين المحليين، تدعم حتى 5 نقاط بيع.',
                'status' => true,
                'features' => [
                    'max_pos_count' => '5',
                    'max_voucher_packages' => '10',
                    'max_monthly_sales_volume' => '1000000.00',
                    'advanced_analytics' => '0',
                    'sms_notifications' => '0',
                    'custom_voucher_template' => '1',
                    'multi_branch_support' => '0',
                    'api_integration' => '0',
                    'priority_support' => '0',
                    'automated_cloud_backup' => '1',
                ],
            ],
            [
                'plan_uuid' => 'plan-business-pro',
                'plan_name' => 'الباقة الاحترافية (Business Pro)',
                'plan_price' => 28000.00,
                'plan_discount' => 10.00,
                'plan_validity' => 30,
                'plan_desc' => 'الخيار الأكثر طلباً للشبكات المتنامية، تدعم 20 نقطة بيع وتحليلات متقدمة.',
                'status' => true,
                'features' => [
                    'max_pos_count' => '20',
                    'max_voucher_packages' => '30',
                    'max_monthly_sales_volume' => '5000000.00',
                    'advanced_analytics' => '1',
                    'sms_notifications' => '1',
                    'custom_voucher_template' => '1',
                    'multi_branch_support' => '1',
                    'api_integration' => '0',
                    'priority_support' => '1',
                    'automated_cloud_backup' => '1',
                ],
            ],
            [
                'plan_uuid' => 'plan-enterprise-tier',
                'plan_name' => 'باقة المؤسسات (Enterprise)',
                'plan_price' => 65000.00,
                'plan_discount' => 15.00,
                'plan_validity' => 30,
                'plan_desc' => 'حل شامل وغير محدود لمشغلي الشبكات الكبرى مع ربط API ودعم فني مخصص.',
                'status' => true,
                'features' => [
                    'max_pos_count' => '999',
                    'max_voucher_packages' => '999',
                    'max_monthly_sales_volume' => '50000000.00',
                    'advanced_analytics' => '1',
                    'sms_notifications' => '1',
                    'custom_voucher_template' => '1',
                    'multi_branch_support' => '1',
                    'api_integration' => '1',
                    'priority_support' => '1',
                    'automated_cloud_backup' => '1',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $featureValues = $planData['features'];
            unset($planData['features']);

            DB::table('subscription_plans')->updateOrInsert(
                ['plan_uuid' => $planData['plan_uuid']],
                array_merge($planData, [
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ])
            );

            $planId = DB::table('subscription_plans')->where('plan_uuid', $planData['plan_uuid'])->value('plan_id');

            foreach ($featureValues as $featureName => $value) {
                $featureId = $features[$featureName] ?? null;
                if (! $featureId) {
                    continue;
                }

                DB::table('subscription_plan_features')->updateOrInsert(
                    [
                        'plan_id' => $planId,
                        'feature_id' => $featureId,
                    ],
                    [
                        'plan_feature_value' => $value,
                        'status' => true,
                        'created_by' => $adminUserId,
                        'updated_at' => now('Asia/Aden'),
                        'created_at' => now('Asia/Aden'),
                    ]
                );
            }
        }
    }
}
