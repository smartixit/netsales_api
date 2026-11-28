<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds stable Billing product/plan identities and the approved launch policies.
 *
 * Commercial plan versions and prices are deliberately not created here:
 * no launch tariff has been supplied as an approved amount. Plans therefore
 * remain inactive and cannot be purchased until Platform Billing publishes
 * a complete version with explicit prices.
 */
class BillingMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now('Asia/Aden');
            $actorId = DB::table('users')
                ->join('roles', 'users.role_id', '=', 'roles.role_id')
                ->where('roles.scope', 'PLATFORM')
                ->where('roles.role_key', 'SUPER_ADMIN')
                ->value('users.user_id');

            $productId = DB::table('billing_products')
                ->where('product_key', 'netsales-b2b-network')
                ->value('product_id');

            if ($productId === null) {
                $productId = DB::table('billing_products')->insertGetId([
                    'product_key' => 'netsales-b2b-network',
                    'name' => 'NetSales B2B Network',
                    'description' => 'اشتراكات منصة NetSales لإدارة شبكات مزودي الخدمة ونقاط البيع والعمليات التجارية.',
                    'is_active' => true,
                    'created_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'product_id');
            }

            $plans = [
                ['starter', 'البداية', 'للشبكات الصغيرة: نقطتا بيع و5 باقات وعضوان إضافيان.', true],
                ['growth', 'النمو', 'للشبكات النامية: 5 نقاط بيع و15 باقة و5 أعضاء إضافيين.', true],
                ['business', 'الأعمال', 'للشبكات متعددة نقاط البيع: 20 نقطة بيع و50 باقة و15 عضواً إضافياً.', true],
                ['enterprise', 'المؤسسات', 'للشبكات الكبيرة وفق حدود وأسعار تعاقدية مخصصة.', false],
            ];

            foreach ($plans as [$key, $name, $description, $public]) {
                DB::table('billing_plans')->updateOrInsert(
                    ['product_id' => $productId, 'plan_key' => $key],
                    [
                        'name' => $name,
                        'description' => $description,
                        'is_public' => $public,
                        'is_active' => false,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            $this->seedTrialPolicy($actorId, $now);
            $this->seedDunningPolicy($actorId, $now);
        }, 3);
    }

    private function seedTrialPolicy(?int $actorId, mixed $now): void
    {
        $policyId = DB::table('billing_trial_policies')
            ->where('policy_key', 'netsales-starter-14-day-trial')
            ->value('trial_policy_id');

        if ($policyId === null) {
            $policyId = DB::table('billing_trial_policies')->insertGetId([
                'policy_key' => 'netsales-starter-14-day-trial',
                'name' => 'تجربة NetSales Starter لمدة 14 يوماً',
                'description' => 'تجربة واحدة لكل شبكة جديدة مؤهلة؛ تبدأ عند شراء Starter وتنتهي بإنشاء فاتورة.',
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'trial_policy_id');
        }

        if (DB::table('billing_trial_policy_versions')->where('trial_policy_id', $policyId)->exists()) {
            return;
        }

        $versionId = DB::table('billing_trial_policy_versions')->insertGetId([
            'trial_policy_id' => $policyId,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'is_enabled' => true,
            'duration_value' => 14,
            'duration_unit' => 'DAY',
            'start_trigger' => 'PURCHASE',
            'redemption_scope' => 'NETWORK',
            'eligibility_mode' => 'NEW_BILLING_ACCOUNT',
            'requires_manual_approval' => false,
            'end_behavior' => 'CREATE_INVOICE',
            'entitlement_mode' => 'FULL_PLAN',
            'published_at' => $now,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'trial_policy_version_id');

        DB::table('billing_trial_policies')->where('trial_policy_id', $policyId)
            ->update(['current_version_id' => $versionId, 'updated_at' => $now]);
    }

    private function seedDunningPolicy(?int $actorId, mixed $now): void
    {
        $policyId = DB::table('billing_dunning_policies')
            ->where('policy_key', 'platform-default')
            ->value('dunning_policy_id');

        if ($policyId === null) {
            $policyId = DB::table('billing_dunning_policies')->insertGetId([
                'policy_key' => 'platform-default',
                'name' => 'سياسة التحصيل الافتراضية لمنصة NetSales',
                'description' => 'فترة سماح 7 أيام مع تذكيرات الاستحقاق والتنبيهات قبل التجديد.',
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'dunning_policy_id');
        }

        if (DB::table('billing_dunning_policy_versions')->where('dunning_policy_id', $policyId)->exists()) {
            return;
        }

        $versionId = DB::table('billing_dunning_policy_versions')->insertGetId([
            'dunning_policy_id' => $policyId,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'grace_period_days' => 7,
            'auto_suspend_after_grace' => true,
            'reminder_schedule_days' => json_encode([0, 2, 5, 7], JSON_THROW_ON_ERROR),
            'pre_renewal_alert_days' => json_encode([7, 3, 1], JSON_THROW_ON_ERROR),
            'published_at' => $now,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'dunning_policy_version_id');

        DB::table('billing_dunning_policies')->where('dunning_policy_id', $policyId)
            ->update(['current_version_id' => $versionId, 'updated_at' => $now]);
    }
}
