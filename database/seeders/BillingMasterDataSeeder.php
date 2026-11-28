<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds the approved Yemen launch catalog and immutable policy versions.
 *
 * Commercial prices are catalogue values in YER, not FX conversions. YER
 * stores two decimal places, so major-unit prices are persisted in minor units.
 * Published versions are never modified by a subsequent seeder run.
 */
class BillingMasterDataSeeder extends Seeder
{
    private const PRODUCT_KEY = 'netsales-b2b-network';

    /**
     * Annual prices equal ten monthly payments (two months free).
     *
     * @var array<string, array{monthly: int, yearly: int, limits: array<string, int>}>
     */
    private const LAUNCH_PLANS = [
        'starter' => [
            'monthly' => 33_000,
            'yearly' => 330_000,
            'limits' => [
                'max_pos_count' => 2,
                'max_voucher_packages' => 5,
                'max_network_members' => 2,
            ],
        ],
        'growth' => [
            'monthly' => 66_000,
            'yearly' => 660_000,
            'limits' => [
                'max_pos_count' => 5,
                'max_voucher_packages' => 15,
                'max_network_members' => 5,
            ],
        ],
        'business' => [
            'monthly' => 124_000,
            'yearly' => 1_240_000,
            'limits' => [
                'max_pos_count' => 20,
                'max_voucher_packages' => 50,
                'max_network_members' => 15,
            ],
        ],
    ];

    private const PLAN_IDENTITIES = [
        'starter' => [
            'name' => 'البداية',
            'description' => 'للشبكات الصغيرة: نقطتا بيع و5 باقات وعضوان إضافيان.',
            'is_public' => true,
        ],
        'growth' => [
            'name' => 'النمو',
            'description' => 'للشبكات النامية: 5 نقاط بيع و15 باقة و5 أعضاء إضافيين.',
            'is_public' => true,
        ],
        'business' => [
            'name' => 'الأعمال',
            'description' => 'للشبكات متعددة نقاط البيع: 20 نقطة بيع و50 باقة و15 عضواً إضافياً.',
            'is_public' => true,
        ],
        'enterprise' => [
            'name' => 'المؤسسات',
            'description' => 'للشبكات الكبيرة وفق حدود وأسعار تعاقدية مخصصة.',
            'is_public' => false,
        ],
    ];

    private const FEATURE_NAMES = [
        'max_pos_count' => ['الحد الأقصى لنقاط البيع المرتبطة بالشبكة', 'POS'],
        'max_voucher_packages' => ['الحد الأقصى لباقات الشبكة', 'package'],
        'max_network_members' => ['الحد الأقصى لأعضاء فريق الشبكة', 'member'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now('Asia/Aden');
            $actorId = $this->platformActorId();
            $productId = $this->ensureProduct($actorId, $now);

            $trialVersionId = $this->seedTrialPolicy($actorId, $now);
            $dunningVersionId = $this->seedDunningPolicy($actorId, $now);
            $yerCurrencyId = DB::table('currencies')
                ->where('code', 'YER')
                ->where('is_active', true)
                ->value('currency_id');

            if (! $yerCurrencyId) {
                throw new RuntimeException('YER must be configured and active before publishing the Yemen launch catalog.');
            }

            foreach (self::PLAN_IDENTITIES as $planKey => $identity) {
                $planId = $this->ensurePlan($productId, $planKey, $identity, $actorId, $now);

                if (! isset(self::LAUNCH_PLANS[$planKey])) {
                    // Enterprise is a negotiated contract, not a zero-priced public subscription.
                    continue;
                }

                $this->publishLaunchPlan(
                    $planId,
                    $planKey,
                    (int) $yerCurrencyId,
                    $trialVersionId,
                    $dunningVersionId,
                    $actorId,
                    $now
                );
            }
        }, 3);
    }

    private function platformActorId(): ?int
    {
        $actorId = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.role_id')
            ->where('roles.scope', 'PLATFORM')
            ->where('roles.role_key', 'SUPER_ADMIN')
            ->value('users.user_id');

        return $actorId === null ? null : (int) $actorId;
    }

    private function ensureProduct(?int $actorId, mixed $now): int
    {
        $product = DB::table('billing_products')
            ->where('product_key', self::PRODUCT_KEY)
            ->first();

        if ($product) {
            if ($product->deleted_at !== null) {
                throw new RuntimeException('The canonical NetSales Billing product is soft-deleted; restore it explicitly before seeding.');
            }

            return (int) $product->product_id;
        }

        return (int) DB::table('billing_products')->insertGetId([
            'product_key' => self::PRODUCT_KEY,
            'name' => 'NetSales B2B Network',
            'description' => 'اشتراكات منصة NetSales لإدارة شبكات مزودي الخدمة ونقاط البيع والعمليات التجارية.',
            'is_active' => true,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'product_id');
    }

    /**
     * @param array{name: string, description: string, is_public: bool} $identity
     */
    private function ensurePlan(
        int $productId,
        string $planKey,
        array $identity,
        ?int $actorId,
        mixed $now
    ): int {
        $plan = DB::table('billing_plans')
            ->where('product_id', $productId)
            ->where('plan_key', $planKey)
            ->first();

        if ($plan) {
            if ($plan->deleted_at !== null) {
                throw new RuntimeException("Billing plan [{$planKey}] is soft-deleted; restore it explicitly before seeding.");
            }

            // Never rewrite a plan identity after it has acquired commercial history.
            if (DB::table('billing_plan_versions')->where('plan_id', $plan->plan_id)->exists()) {
                return (int) $plan->plan_id;
            }

            DB::table('billing_plans')->where('plan_id', $plan->plan_id)->update([
                ...$identity,
                'is_active' => false,
                'updated_at' => $now,
            ]);

            return (int) $plan->plan_id;
        }

        return (int) DB::table('billing_plans')->insertGetId([
            'product_id' => $productId,
            'plan_key' => $planKey,
            ...$identity,
            'is_active' => false,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'plan_id');
    }

    private function publishLaunchPlan(
        int $planId,
        string $planKey,
        int $currencyId,
        ?int $trialVersionId,
        int $dunningVersionId,
        ?int $actorId,
        mixed $now
    ): void {
        $existingVersion = DB::table('billing_plan_versions')
            ->where('plan_id', $planId)
            ->orderByDesc('version_number')
            ->first();

        if ($existingVersion) {
            // Published, archived, and manually-created drafts are owned by Billing workflows.
            return;
        }

        $definition = self::LAUNCH_PLANS[$planKey];
        $planTrialVersionId = $planKey === 'starter' ? $trialVersionId : null;
        $priceSnapshot = [
            'currency' => 'YER',
            'monthly_amount' => $definition['monthly'],
            'yearly_amount' => $definition['yearly'],
            'setup_fee' => 0,
            'tax_behavior' => 'EXCLUSIVE',
            'annual_discount_months' => 2,
        ];

        $versionId = (int) DB::table('billing_plan_versions')->insertGetId([
            'plan_id' => $planId,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'trial_policy_version_id' => $planTrialVersionId,
            'dunning_policy_version_id' => $dunningVersionId,
            'terms_snapshot' => json_encode([
                'plan_key' => $planKey,
                'currency' => 'YER',
                'prices' => $priceSnapshot,
                'limits' => $definition['limits'],
                'trial_policy_version_id' => $planTrialVersionId,
                'dunning_policy_version_id' => $dunningVersionId,
            ], JSON_THROW_ON_ERROR),
            'published_at' => $now,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'plan_version_id');

        foreach ([
            'MONTH' => $definition['monthly'],
            'YEAR' => $definition['yearly'],
        ] as $interval => $amount) {
            DB::table('billing_prices')->insert([
                'plan_version_id' => $versionId,
                'currency_id' => $currencyId,
                'billing_interval' => $interval,
                'interval_count' => 1,
                'unit_amount_minor' => $amount * 100,
                'setup_fee_minor' => 0,
                'tax_behavior' => 'EXCLUSIVE',
                'trial_policy_version_id' => $planTrialVersionId,
                'dunning_policy_version_id' => $dunningVersionId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($definition['limits'] as $featureKey => $limit) {
            [$name, $unit] = self::FEATURE_NAMES[$featureKey];

            DB::table('billing_plan_features')->insert([
                'plan_version_id' => $versionId,
                'feature_key' => $featureKey,
                'name' => $name,
                'feature_type' => 'RESOURCE_LIMIT',
                'value' => (string) $limit,
                'unit' => $unit,
                'aggregation' => null,
                'reset_period' => null,
                'overage_behavior' => 'BLOCK',
                'configuration' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('billing_plans')->where('plan_id', $planId)->update([
            'current_version_id' => $versionId,
            'is_active' => true,
            'updated_at' => $now,
        ]);
    }

    private function seedTrialPolicy(?int $actorId, mixed $now): int
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

        $versionId = DB::table('billing_trial_policy_versions')
            ->where('trial_policy_id', $policyId)
            ->where('status', 'PUBLISHED')
            ->orderByDesc('version_number')
            ->value('trial_policy_version_id');

        if ($versionId !== null) {
            DB::table('billing_trial_policies')->where('trial_policy_id', $policyId)
                ->update(['current_version_id' => $versionId, 'is_active' => true, 'updated_at' => $now]);

            return (int) $versionId;
        }

        if (DB::table('billing_trial_policy_versions')->where('trial_policy_id', $policyId)->exists()) {
            throw new RuntimeException('Starter trial policy has a non-published version; refusing to overwrite or bypass its review state.');
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
            ->update(['current_version_id' => $versionId, 'is_active' => true, 'updated_at' => $now]);

        return (int) $versionId;
    }

    private function seedDunningPolicy(?int $actorId, mixed $now): int
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

        $versionId = DB::table('billing_dunning_policy_versions')
            ->where('dunning_policy_id', $policyId)
            ->where('status', 'PUBLISHED')
            ->orderByDesc('version_number')
            ->value('dunning_policy_version_id');

        if ($versionId !== null) {
            DB::table('billing_dunning_policies')->where('dunning_policy_id', $policyId)
                ->update(['current_version_id' => $versionId, 'is_active' => true, 'updated_at' => $now]);

            return (int) $versionId;
        }

        if (DB::table('billing_dunning_policy_versions')->where('dunning_policy_id', $policyId)->exists()) {
            throw new RuntimeException('Default dunning policy has a non-published version; refusing to overwrite or bypass its review state.');
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
            ->update(['current_version_id' => $versionId, 'is_active' => true, 'updated_at' => $now]);

        return (int) $versionId;
    }
}
