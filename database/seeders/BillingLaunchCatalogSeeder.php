<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingLaunchCatalogSeeder extends Seeder
{
    /**
     * Seeds the first published commercial catalogue. Published versions are
     * immutable; subsequent pricing changes require a new plan version.
     */
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

                DB::table('billing_products')
                    ->where('product_id', $productId)
                    ->update(['deleted_at' => null, 'is_active' => true, 'updated_at' => $now]);
            }

            $trialPolicyVersionId = $this->ensureStarterTrialPolicy($actorId, $now);
            $dunningVersionId = $this->ensureDefaultDunningPolicy($actorId, $now);

            $plans = [
                [
                    'key' => 'starter',
                    'name' => 'البداية',
                    'description' => 'للشبكات الصغيرة: نقطتا بيع، 5 باقات، وعضوان إضافيان في فريق الشبكة. تشمل تجربة مجانية لمدة 14 يوماً للمؤهلين.',
                    'pos_limit' => '2',
                    'package_limit' => '5',
                    'member_limit' => '2',
                    'trial_policy_version_id' => $trialPolicyVersionId,
                    'public' => true,
                    'prices' => [
                        'SAR' => ['monthly' => 5900, 'yearly' => 59000],
                        'USD' => ['monthly' => 1600, 'yearly' => 16000],
                        'AED' => ['monthly' => 5900, 'yearly' => 59000],
                    ],
                ],
                [
                    'key' => 'growth',
                    'name' => 'النمو',
                    'description' => 'للشبكات النامية: 5 نقاط بيع، 15 باقة، و5 أعضاء إضافيين في فريق الشبكة.',
                    'pos_limit' => '5',
                    'package_limit' => '15',
                    'member_limit' => '5',
                    'trial_policy_version_id' => null,
                    'public' => true,
                    'prices' => [
                        'SAR' => ['monthly' => 11900, 'yearly' => 119000],
                        'USD' => ['monthly' => 3200, 'yearly' => 32000],
                        'AED' => ['monthly' => 11700, 'yearly' => 117000],
                    ],
                ],
                [
                    'key' => 'business',
                    'name' => 'الأعمال',
                    'description' => 'للشبكات متعددة نقاط البيع: 20 نقطة بيع، 50 باقة، و15 عضواً إضافياً في فريق الشبكة.',
                    'pos_limit' => '20',
                    'package_limit' => '50',
                    'member_limit' => '15',
                    'trial_policy_version_id' => null,
                    'public' => true,
                    'prices' => [
                        'SAR' => ['monthly' => 23900, 'yearly' => 239000],
                        'USD' => ['monthly' => 6400, 'yearly' => 64000],
                        'AED' => ['monthly' => 23500, 'yearly' => 235000],
                    ],
                ],
                [
                    'key' => 'enterprise',
                    'name' => 'المؤسسات',
                    'description' => 'للشبكات الكبيرة: حدود موارد وشروط تعاقدية مخصصة، وتسعير عبر عرض سعر واتفاقية خدمة.',
                    'pos_limit' => '-1',
                    'package_limit' => '-1',
                    'member_limit' => '-1',
                    'trial_policy_version_id' => null,
                    'public' => false,
                    'prices' => [],
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
                        'is_public' => $definition['public'],
                        'is_active' => true,
                        'created_by' => $actorId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'plan_id');
                } else {
                    $planId = (int) $plan->plan_id;

                    DB::table('billing_plans')
                        ->where('plan_id', $planId)
                        ->update([
                            'deleted_at' => null,
                            'is_public' => $definition['public'],
                            'is_active' => true,
                            'updated_at' => $now,
                        ]);
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
                        'dunning_policy_version_id' => $dunningVersionId,
                        'created_by' => $actorId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'plan_version_id');
                    $versionStatus = 'DRAFT';
                } else {
                    $versionId = (int) $version->plan_version_id;
                    $versionStatus = (string) $version->status;

                    if ($versionStatus === 'DRAFT') {
                        DB::table('billing_plan_versions')
                            ->where('plan_version_id', $versionId)
                            ->update([
                                'trial_policy_version_id' => $definition['trial_policy_version_id'],
                                'dunning_policy_version_id' => $dunningVersionId,
                                'updated_at' => $now,
                            ]);
                    }
                }

                if ($versionStatus !== 'DRAFT') {
                    // Re-running the seeder must never rewrite an immutable version.
                    continue;
                }

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
                $this->ensureResourceLimit(
                    $versionId,
                    'max_network_members',
                    'الحد الأقصى لأعضاء فريق الشبكة النشطين أو المدعوين',
                    $definition['member_limit'],
                    'member',
                    $now
                );

                foreach ($definition['prices'] as $currencyCode => $prices) {
                    $currencyId = DB::table('currencies')
                        ->where('code', $currencyCode)
                        ->where('is_active', true)
                        ->value('currency_id');

                    if (! $currencyId) {
                        throw new RuntimeException("Required billing currency [{$currencyCode}] is missing or inactive.");
                    }

                    $this->ensurePrice(
                        $versionId,
                        (int) $currencyId,
                        'MONTH',
                        (int) $prices['monthly'],
                        $now
                    );
                    $this->ensurePrice(
                        $versionId,
                        (int) $currencyId,
                        'YEAR',
                        (int) $prices['yearly'],
                        $now
                    );
                }

                if ($definition['public'] && $definition['prices'] !== []) {
                    $hasPrice = DB::table('billing_prices')
                        ->where('plan_version_id', $versionId)
                        ->where('is_active', true)
                        ->exists();

                    if (! $hasPrice) {
                        throw new RuntimeException("Plan [{$definition['key']}] cannot be published without an active price.");
                    }

                    DB::table('billing_plan_versions')
                        ->where('plan_version_id', $versionId)
                        ->update([
                            'status' => 'PUBLISHED',
                            'published_at' => $now,
                            'updated_at' => $now,
                        ]);

                    DB::table('billing_plans')
                        ->where('plan_id', $planId)
                        ->update([
                            'current_version_id' => $versionId,
                            'updated_at' => $now,
                        ]);
                }
            }
        }, 3);
    }

    private function ensureStarterTrialPolicy(mixed $actorId, mixed $now): int
    {
        $policy = DB::table('billing_trial_policies')
            ->where('policy_key', 'netsales-starter-14-day-trial')
            ->first();

        if (! $policy) {
            $policyId = DB::table('billing_trial_policies')->insertGetId([
                'policy_key' => 'netsales-starter-14-day-trial',
                'name' => 'تجربة NetSales Starter لمدة 14 يوماً',
                'description' => 'تجربة واحدة لكل شبكة جديدة مؤهلة، تبدأ عند شراء Starter دون تحصيل خلال فترة التجربة، ثم تنشأ فاتورة للفترة المدفوعة.',
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'trial_policy_id');
        } else {
            $policyId = (int) $policy->trial_policy_id;
            DB::table('billing_trial_policies')
                ->where('trial_policy_id', $policyId)
                ->update(['is_active' => true, 'updated_at' => $now]);
        }

        $version = DB::table('billing_trial_policy_versions')
            ->where('trial_policy_id', $policyId)
            ->where('version_number', 1)
            ->first();

        if (! $version) {
            $versionId = DB::table('billing_trial_policy_versions')->insertGetId([
                'trial_policy_id' => $policyId,
                'version_number' => 1,
                'status' => 'DRAFT',
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
                'published_at' => null,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'trial_policy_version_id');
        } else {
            $versionId = (int) $version->trial_policy_version_id;

            if ($version->status === 'DRAFT') {
                DB::table('billing_trial_policy_versions')
                    ->where('trial_policy_version_id', $versionId)
                    ->update([
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
                        'status' => 'PUBLISHED',
                        'updated_at' => $now,
                    ]);
            }
        }

        $published = DB::table('billing_trial_policy_versions')
            ->where('trial_policy_version_id', $versionId)
            ->where('status', 'PUBLISHED')
            ->exists();

        if (! $published) {
            DB::table('billing_trial_policy_versions')
                ->where('trial_policy_version_id', $versionId)
                ->update([
                    'status' => 'PUBLISHED',
                    'is_enabled' => true,
                    'published_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        $policy->current_version_id ?? null;
        if (! DB::table('billing_trial_policies')
            ->where('trial_policy_id', $policyId)
            ->value('current_version_id')) {
            DB::table('billing_trial_policies')
                ->where('trial_policy_id', $policyId)
                ->update(['current_version_id' => $versionId, 'updated_at' => $now]);
        }

        return $versionId;
    }

    private function ensureDefaultDunningPolicy(mixed $actorId, mixed $now): int
    {
        $policy = DB::table('billing_dunning_policies')
            ->where('policy_key', 'platform-default')
            ->first();

        if (! $policy) {
            $policyId = DB::table('billing_dunning_policies')->insertGetId([
                'policy_key' => 'platform-default',
                'name' => 'سياسة التحصيل الافتراضية للمنصة',
                'description' => 'تذكيرات منظمة بعد الاستحقاق، فترة سماح لمدة 7 أيام، وتنبيه قبل التجديد.',
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'dunning_policy_id');
        } else {
            $policyId = (int) $policy->dunning_policy_id;
            DB::table('billing_dunning_policies')
                ->where('dunning_policy_id', $policyId)
                ->update(['is_active' => true, 'updated_at' => $now]);
        }

        $version = DB::table('billing_dunning_policy_versions')
            ->where('dunning_policy_id', $policyId)
            ->where('version_number', 1)
            ->first();

        if (! $version) {
            $versionId = DB::table('billing_dunning_policy_versions')->insertGetId([
                'dunning_policy_id' => $policyId,
                'version_number' => 1,
                'status' => 'DRAFT',
                'grace_period_days' => 7,
                'auto_suspend_after_grace' => true,
                'reminder_schedule_days' => json_encode([0, 2, 5, 7], JSON_THROW_ON_ERROR),
                'pre_renewal_alert_days' => json_encode([7, 3, 1], JSON_THROW_ON_ERROR),
                'published_at' => null,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'dunning_policy_version_id');
        } else {
            $versionId = (int) $version->dunning_policy_version_id;

            if ($version->status === 'DRAFT') {
                DB::table('billing_dunning_policy_versions')
                    ->where('dunning_policy_version_id', $versionId)
                    ->update([
                        'grace_period_days' => 7,
                        'auto_suspend_after_grace' => true,
                        'reminder_schedule_days' => json_encode([0, 2, 5, 7], JSON_THROW_ON_ERROR),
                        'pre_renewal_alert_days' => json_encode([7, 3, 1], JSON_THROW_ON_ERROR),
                        'status' => 'PUBLISHED',
                        'published_at' => $now,
                        'updated_at' => $now,
                    ]);
            }
        }

        if (! DB::table('billing_dunning_policy_versions')
            ->where('dunning_policy_version_id', $versionId)
            ->where('status', 'PUBLISHED')
            ->exists()) {
            DB::table('billing_dunning_policy_versions')
                ->where('dunning_policy_version_id', $versionId)
                ->update([
                    'status' => 'PUBLISHED',
                    'published_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        if (! DB::table('billing_dunning_policies')
            ->where('dunning_policy_id', $policyId)
            ->value('current_version_id')) {
            DB::table('billing_dunning_policies')
                ->where('dunning_policy_id', $policyId)
                ->update(['current_version_id' => $versionId, 'updated_at' => $now]);
        }

        return $versionId;
    }

    private function ensurePrice(
        int $planVersionId,
        int $currencyId,
        string $interval,
        int $amountMinor,
        mixed $now
    ): void {
        $price = DB::table('billing_prices')
            ->where('plan_version_id', $planVersionId)
            ->where('currency_id', $currencyId)
            ->where('billing_interval', $interval)
            ->where('interval_count', 1)
            ->first();

        if ($price) {
            DB::table('billing_prices')
                ->where('price_id', $price->price_id)
                ->update([
                    'unit_amount_minor' => $amountMinor,
                    'setup_fee_minor' => 0,
                    'tax_behavior' => 'EXCLUSIVE',
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('billing_prices')->insert([
            'plan_version_id' => $planVersionId,
            'currency_id' => $currencyId,
            'billing_interval' => $interval,
            'interval_count' => 1,
            'unit_amount_minor' => $amountMinor,
            'setup_fee_minor' => 0,
            'tax_behavior' => 'EXCLUSIVE',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
