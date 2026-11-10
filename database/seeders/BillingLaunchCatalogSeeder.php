<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds immutable catalog definitions as drafts only.
 *
 * This seeder never publishes a plan or policy version and never changes a
 * current_version_id pointer. Publishing is an explicit Platform Billing
 * operation after commercial and policy review.
 */
class BillingLaunchCatalogSeeder extends Seeder
{
    private const PRODUCT_KEY = 'netsales-b2b-network';

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now('Asia/Aden');
            $actorId = $this->platformActorId();
            $productId = $this->ensureProduct($actorId, $now);
            $trialVersionId = $this->ensureStarterTrialDraft($actorId, $now);
            $dunningVersionId = $this->ensureLaunchDunningDraft($actorId, $now);

            foreach ($this->planDefinitions() as $definition) {
                if ($dunningVersionId === null) {
                    $this->command?->warn(
                        'Skipped plan catalog: the launch dunning policy has no editable draft. Create a new policy version through Platform Billing.'
                    );
                    break;
                }

                if ($definition['trial_policy_version_id'] === 'starter' && $trialVersionId === null) {
                    $this->command?->warn(
                        'Skipped [starter]: the Starter trial policy has no editable draft. Create a new policy version through Platform Billing.'
                    );
                    continue;
                }

                $planId = $this->ensurePlan($productId, $definition, $actorId, $now);
                $planVersionId = $this->ensureDraftPlanVersion(
                    $planId,
                    $definition['key'],
                    $definition['trial_policy_version_id'] === 'starter'
                        ? $trialVersionId
                        : null,
                    $dunningVersionId,
                    $actorId,
                    $now
                );

                // A plan with an existing published/archived version is immutable.
                // Do not mutate it or manufacture a new commercial version in a seeder.
                if ($planVersionId === null) {
                    continue;
                }

                foreach ($definition['limits'] as [$featureKey, $name, $value, $unit]) {
                    $this->ensureResourceLimit(
                        $planVersionId,
                        $featureKey,
                        $name,
                        $value,
                        $unit,
                        $now
                    );
                }

                foreach ($definition['prices'] as $currencyCode => $priceSet) {
                    $currencyId = DB::table('currencies')
                        ->where('code', $currencyCode)
                        ->where('is_active', true)
                        ->value('currency_id');

                    if (! $currencyId) {
                        throw new RuntimeException(
                            "Required billing currency [{$currencyCode}] is missing or inactive."
                        );
                    }

                    $this->ensurePrice(
                        $planVersionId,
                        (int) $currencyId,
                        'MONTH',
                        (int) $priceSet['monthly_minor'],
                        $now
                    );
                    $this->ensurePrice(
                        $planVersionId,
                        (int) $currencyId,
                        'YEAR',
                        (int) $priceSet['yearly_minor'],
                        $now
                    );
                }
            }
        }, 3);
    }

    private function platformActorId(): ?int
    {
        $actorId = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.role_id')
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
                throw new RuntimeException(
                    'The canonical NetSales billing product is soft-deleted. Restore it explicitly before seeding.'
                );
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

    private function ensurePlan(
        int $productId,
        array $definition,
        ?int $actorId,
        mixed $now
    ): int {
        $plan = DB::table('billing_plans')
            ->where('product_id', $productId)
            ->where('plan_key', $definition['key'])
            ->first();

        if ($plan) {
            if ($plan->deleted_at !== null) {
                throw new RuntimeException(
                    "Billing plan [{$definition['key']}] is soft-deleted. Restore it explicitly before seeding."
                );
            }

            return (int) $plan->plan_id;
        }

        return (int) DB::table('billing_plans')->insertGetId([
            'product_id' => $productId,
            'plan_key' => $definition['key'],
            'name' => $definition['name'],
            'description' => $definition['description'],
            'is_public' => $definition['is_public'],
            'is_active' => true,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'plan_id');
    }

    private function ensureDraftPlanVersion(
        int $planId,
        string $planKey,
        ?int $trialPolicyVersionId,
        int $dunningPolicyVersionId,
        ?int $actorId,
        mixed $now
    ): ?int {
        $versions = DB::table('billing_plan_versions')
            ->where('plan_id', $planId)
            ->orderByDesc('version_number')
            ->get();

        $draft = $versions->firstWhere('status', 'DRAFT');
        if ($draft) {
            // Keep any existing draft edits; seeding is additive and non-destructive.
            return (int) $draft->plan_version_id;
        }

        if ($versions->isNotEmpty()) {
            $this->command?->warn(
                "Skipped [{$planKey}]: existing plan versions are immutable; no draft version was changed or created."
            );

            return null;
        }

        return (int) DB::table('billing_plan_versions')->insertGetId([
            'plan_id' => $planId,
            'version_number' => 1,
            'status' => 'DRAFT',
            'trial_policy_version_id' => $trialPolicyVersionId,
            'dunning_policy_version_id' => $dunningPolicyVersionId,
            'published_at' => null,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'plan_version_id');
    }

    private function ensureStarterTrialDraft(?int $actorId, mixed $now): ?int
    {
        $policy = DB::table('billing_trial_policies')
            ->where('policy_key', 'netsales-starter-14-day-trial')
            ->first();

        if (! $policy) {
            $policyId = (int) DB::table('billing_trial_policies')->insertGetId([
                'policy_key' => 'netsales-starter-14-day-trial',
                'name' => 'تجربة NetSales Starter لمدة 14 يوماً',
                'description' => 'تجربة واحدة لكل شبكة جديدة مؤهلة؛ تبدأ عند شراء Starter وتنتهي بتحويلها إلى دورة مدفوعة وفق السياسة المنشورة.',
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'trial_policy_id');
        } else {
            $policyId = (int) $policy->trial_policy_id;
        }

        return $this->ensurePolicyDraftVersion(
            'billing_trial_policy_versions',
            'trial_policy_version_id',
            'trial_policy_id',
            $policyId,
            [
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
            ],
            $actorId,
            $now
        );
    }

    private function ensureLaunchDunningDraft(?int $actorId, mixed $now): ?int
    {
        $policy = DB::table('billing_dunning_policies')
            ->where('policy_key', 'netsales-launch-dunning')
            ->first();

        if (! $policy) {
            $policyId = (int) DB::table('billing_dunning_policies')->insertGetId([
                'policy_key' => 'netsales-launch-dunning',
                'name' => 'سياسة التحصيل لإطلاق NetSales',
                'description' => 'مسودة سياسة تحصيل الإطلاق: فترة سماح 7 أيام، تذكيرات عند الاستحقاق وبعد يومين وخمسة وسبعة أيام، وتنبيهات قبل التجديد.',
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'dunning_policy_id');
        } else {
            $policyId = (int) $policy->dunning_policy_id;
        }

        return $this->ensurePolicyDraftVersion(
            'billing_dunning_policy_versions',
            'dunning_policy_version_id',
            'dunning_policy_id',
            $policyId,
            [
                'grace_period_days' => 7,
                'auto_suspend_after_grace' => true,
                'reminder_schedule_days' => json_encode([0, 2, 5, 7], JSON_THROW_ON_ERROR),
                'pre_renewal_alert_days' => json_encode([7, 3, 1], JSON_THROW_ON_ERROR),
            ],
            $actorId,
            $now
        );
    }

    /**
     * Returns an existing draft without overwriting it. If a policy has only
     * its original immutable version, creates the next draft exactly once.
     * When multiple immutable versions exist, seeding stops rather than
     * manufacturing new versions on every run.
     *
     * @param array<string, mixed> $values
     */
    private function ensurePolicyDraftVersion(
        string $table,
        string $primaryKey,
        string $foreignKey,
        int $policyId,
        array $values,
        ?int $actorId,
        mixed $now
    ): ?int {
        $versions = DB::table($table)
            ->where($foreignKey, $policyId)
            ->orderByDesc('version_number')
            ->get();

        $draft = $versions->firstWhere('status', 'DRAFT');
        if ($draft) {
            return (int) $draft->{$primaryKey};
        }

        if ($versions->count() > 1) {
            return null;
        }

        $nextVersion = $versions->isEmpty()
            ? 1
            : ((int) $versions->first()->version_number + 1);

        return (int) DB::table($table)->insertGetId([
            $foreignKey => $policyId,
            'version_number' => $nextVersion,
            'status' => 'DRAFT',
            ...$values,
            'published_at' => null,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $primaryKey);
    }

    private function planDefinitions(): array
    {
        return [
            [
                'key' => 'starter',
                'name' => 'البداية',
                'description' => 'للشبكات الصغيرة: نقطتا بيع، 5 باقات، وعضوان إضافيان في فريق الشبكة. يتضمن إعداد تجربة لمدة 14 يوماً للمؤهلين.',
                'is_public' => true,
                'trial_policy_version_id' => 'starter',
                'limits' => [
                    ['max_pos_count', 'الحد الأقصى لنقاط البيع المرتبطة بالشبكة', '2', 'POS'],
                    ['max_voucher_packages', 'الحد الأقصى لباقات الشبكة', '5', 'package'],
                    ['max_network_members', 'الحد الأقصى لأعضاء فريق الشبكة النشطين أو المدعوين', '2', 'member'],
                ],
                'prices' => [
                    'SAR' => ['monthly_minor' => 7900, 'yearly_minor' => 79000],
                    'USD' => ['monthly_minor' => 2100, 'yearly_minor' => 21000],
                    'AED' => ['monthly_minor' => 7900, 'yearly_minor' => 79000],
                ],
            ],
            [
                'key' => 'growth',
                'name' => 'النمو',
                'description' => 'للشبكات النامية: 5 نقاط بيع، 15 باقة، و5 أعضاء إضافيين في فريق الشبكة.',
                'is_public' => true,
                'trial_policy_version_id' => null,
                'limits' => [
                    ['max_pos_count', 'الحد الأقصى لنقاط البيع المرتبطة بالشبكة', '5', 'POS'],
                    ['max_voucher_packages', 'الحد الأقصى لباقات الشبكة', '15', 'package'],
                    ['max_network_members', 'الحد الأقصى لأعضاء فريق الشبكة النشطين أو المدعوين', '5', 'member'],
                ],
                'prices' => [
                    'SAR' => ['monthly_minor' => 15900, 'yearly_minor' => 159000],
                    'USD' => ['monthly_minor' => 4200, 'yearly_minor' => 42000],
                    'AED' => ['monthly_minor' => 15900, 'yearly_minor' => 159000],
                ],
            ],
            [
                'key' => 'business',
                'name' => 'الأعمال',
                'description' => 'للشبكات متعددة نقاط البيع: 20 نقطة بيع، 50 باقة، و15 عضواً إضافياً في فريق الشبكة.',
                'is_public' => true,
                'trial_policy_version_id' => null,
                'limits' => [
                    ['max_pos_count', 'الحد الأقصى لنقاط البيع المرتبطة بالشبكة', '20', 'POS'],
                    ['max_voucher_packages', 'الحد الأقصى لباقات الشبكة', '50', 'package'],
                    ['max_network_members', 'الحد الأقصى لأعضاء فريق الشبكة النشطين أو المدعوين', '15', 'member'],
                ],
                'prices' => [
                    'SAR' => ['monthly_minor' => 29900, 'yearly_minor' => 299000],
                    'USD' => ['monthly_minor' => 7900, 'yearly_minor' => 79000],
                    'AED' => ['monthly_minor' => 29900, 'yearly_minor' => 299000],
                ],
            ],
            [
                'key' => 'enterprise',
                'name' => 'المؤسسات',
                'description' => 'للشبكات الكبيرة ذات المتطلبات التعاقدية والتشغيلية المخصصة؛ تحدد الحدود والأسعار بعد دراسة الاستخدام والاتفاقية.',
                'is_public' => false,
                'trial_policy_version_id' => null,
                'limits' => [
                    ['max_pos_count', 'الحد الأقصى لنقاط البيع المرتبطة بالشبكة', '-1', 'POS'],
                    ['max_voucher_packages', 'الحد الأقصى لباقات الشبكة', '-1', 'package'],
                    ['max_network_members', 'الحد الأقصى لأعضاء فريق الشبكة النشطين أو المدعوين', '-1', 'member'],
                ],
                'prices' => [],
            ],
        ];
    }

    private function ensurePrice(
        int $planVersionId,
        int $currencyId,
        string $interval,
        int $amountMinor,
        mixed $now
    ): void {
        $exists = DB::table('billing_prices')
            ->where('plan_version_id', $planVersionId)
            ->where('currency_id', $currencyId)
            ->where('billing_interval', $interval)
            ->where('interval_count', 1)
            ->exists();

        if ($exists) {
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
