<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prepares the first NetSales B2B subscription catalog.
 *
 * Plans intentionally remain DRAFT and have no prices until Platform Billing
 * configures a price for each supported currency and billing interval.
 */
class BillingLaunchCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now('Asia/Aden');
            $actorId = DB::table('users')->orderBy('user_id')->value('user_id');

            $product = DB::table('billing_products')
                ->where('product_key', 'netsales-b2b-network')
                ->first();

            if (! $product) {
                $productId = DB::table('billing_products')->insertGetId([
                    'product_key' => 'netsales-b2b-network',
                    'name' => 'NetSales B2B Network',
                    'description' => 'اشتراكات منصة NetSales لإدارة شبكات مزودي الخدمة ونقاط البيع والعمليات التجارية.',
                    'is_active' => true,
                    'created_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'product_id');
            } else {
                $productId = (int) $product->product_id;

                if ($product->deleted_at !== null) {
                    DB::table('billing_products')
                        ->where('product_id', $productId)
                        ->update(['deleted_at' => null, 'updated_at' => $now]);
                }
            }

            $trialPolicyVersionId = $this->ensureStarterTrialPolicy($actorId, $now);
            $dunningVersionId = DB::table('billing_dunning_policies')
                ->where('policy_key', 'platform-default')
                ->where('is_active', true)
                ->value('current_version_id');

            if (! $dunningVersionId) {
                throw new RuntimeException('The published platform-default dunning policy must exist before seeding the launch catalog.');
            }

            $plans = [
                [
                    'key' => 'starter',
                    'name' => 'البداية',
                    'description' => 'للشبكات الصغيرة التي تبدأ تشغيلها التجاري؛ حد مقترح قدره نقطتا بيع و5 باقات.',
                    'pos_limit' => '2',
                    'package_limit' => '5',
                    'trial_policy_version_id' => $trialPolicyVersionId,
                ],
                [
                    'key' => 'growth',
                    'name' => 'النمو',
                    'description' => 'للشبكات النامية؛ حد مقترح قدره 5 نقاط بيع و15 باقة.',
                    'pos_limit' => '5',
                    'package_limit' => '15',
                    'trial_policy_version_id' => null,
                ],
                [
                    'key' => 'business',
                    'name' => 'الأعمال',
                    'description' => 'للشبكات متعددة نقاط البيع؛ حد مقترح قدره 20 نقطة بيع و50 باقة.',
                    'pos_limit' => '20',
                    'package_limit' => '50',
                    'trial_policy_version_id' => null,
                ],
                [
                    'key' => 'enterprise',
                    'name' => 'المؤسسات',
                    'description' => 'للشبكات الكبيرة بعقد مخصص؛ حدود الموارد غير مقيدة وتحدد الشروط التجارية لكل عميل.',
                    'pos_limit' => '-1',
                    'package_limit' => '-1',
                    'trial_policy_version_id' => null,
                ],
            ];

            foreach ($plans as $definition) {
                $plan = DB::table('billing_plans')
                    ->where('product_id', $productId)
                    ->where('plan_key', $definition['key'])
                    ->first();

                if (! $plan) {
                    $planId = DB::table('billing_plans')->insertGetId([
                        'product_id' => $productId,
                        'plan_key' => $definition['key'],
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'is_public' => true,
                        'is_active' => true,
                        'created_by' => $actorId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'plan_id');
                } else {
                    $planId = (int) $plan->plan_id;

                    if ($plan->deleted_at !== null) {
                        DB::table('billing_plans')
                            ->where('plan_id', $planId)
                            ->update(['deleted_at' => null, 'updated_at' => $now]);
                    }
                }

                $version = DB::table('billing_plan_versions')
                    ->where('plan_id', $planId)
                    ->where('version_number', 1)
                    ->first();

                if (! $version) {
                    $versionId = DB::table('billing_plan_versions')->insertGetId([
                        'plan_id' => $planId,
                        'version_number' => 1,
                        'status' => 'DRAFT',
                        'trial_policy_version_id' => $definition['trial_policy_version_id'],
                        'dunning_policy_version_id' => (int) $dunningVersionId,
                        'created_by' => $actorId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'plan_version_id');
                } else {
                    $versionId = (int) $version->plan_version_id;

                    // A published commercial version is immutable. Only complete
                    // the policy references of an existing draft version.
                    if ($version->status === 'DRAFT') {
                        DB::table('billing_plan_versions')
                            ->where('plan_version_id', $versionId)
                            ->update([
                                'trial_policy_version_id' => $definition['trial_policy_version_id'],
                                'dunning_policy_version_id' => (int) $dunningVersionId,
                                'updated_at' => $now,
                            ]);
                    }
                }

                $versionStatus = $version->status ?? 'DRAFT';
                if ($versionStatus === 'DRAFT') {
                    $this->ensureResourceLimit(
                        $versionId,
                        'max_pos_count',
                        'الحد الأقصى لنقاط البيع المرتبطة بالشبكة',
                        $definition['pos_limit'],
                        'POS',
                        $now
                    );
                    $this->ensureResourceLimit(
                        $versionId,
                        'max_voucher_packages',
                        'الحد الأقصى لباقات الشبكة',
                        $definition['package_limit'],
                        'package',
                        $now
                    );
                }
            }
        }, 3);
    }

    private function ensureStarterTrialPolicy(mixed $actorId, mixed $now): int
    {
        $policy = DB::table('billing_trial_policies')
            ->where('policy_key', 'netsales-starter-14-day-trial')
            ->first();

        $currentVersionId = $policy?->current_version_id;

        if (! $policy) {
            $policyId = DB::table('billing_trial_policies')->insertGetId([
                'policy_key' => 'netsales-starter-14-day-trial',
                'name' => 'تجربة NetSales Starter لمدة 14 يوماً',
                'description' => 'تجربة واحدة لكل شبكة جديدة، تبدأ عند شراء خطة Starter وتتحول إلى فاتورة عند انتهائها.',
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'trial_policy_id');
        } else {
            $policyId = (int) $policy->trial_policy_id;
        }

        $version = DB::table('billing_trial_policy_versions')
            ->where('trial_policy_id', $policyId)
            ->where('version_number', 1)
            ->first();

        if (! $version) {
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
                'entitlement_config' => null,
                'published_at' => $now,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'trial_policy_version_id');
        } else {
            $versionId = (int) $version->trial_policy_version_id;
        }

        if (! $currentVersionId) {
            DB::table('billing_trial_policies')
                ->where('trial_policy_id', $policyId)
                ->update(['current_version_id' => $versionId, 'updated_at' => $now]);
        }

        return $versionId;
    }

    private function ensureResourceLimit(
        int $planVersionId,
        string $featureKey,
        string $name,
        string $value,
        string $unit,
        mixed $now
    ): void {
        $exists = DB::table('billing_plan_features')
            ->where('plan_version_id', $planVersionId)
            ->where('feature_key', $featureKey)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('billing_plan_features')->insert([
            'plan_version_id' => $planVersionId,
            'feature_key' => $featureKey,
            'name' => $name,
            'feature_type' => 'RESOURCE_LIMIT',
            'value' => $value,
            'unit' => $unit,
            'aggregation' => null,
            'reset_period' => null,
            'overage_behavior' => 'BLOCK',
            'configuration' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
