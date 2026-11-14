<?php

namespace App\Services\Billing;

use App\Enums\{BillingFeatureType, BillingInterval, BillingPlanVersionStatus};
use App\Interfaces\{BillingCatalogRepositoryInterface, CurrencyRepositoryInterface};
use App\Models\{BillingDunningPolicyVersion, BillingPlan, BillingPlanFeature, BillingPlanVersion, BillingPrice, BillingProduct, BillingTrialPolicyVersion, User};
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingCatalogService
{
    public function __construct(
        private BillingCatalogRepositoryInterface $catalog,
        private CurrencyRepositoryInterface $currencies,
        private BillingAuthorizationService $authorization
    ) {}

    /** @param array<string, mixed> $data */
    public function createProduct(array $data, int $actorId): BillingProduct
    {
        $actor = $this->platformActor($actorId);

        return DB::transaction(fn (): BillingProduct => $this->catalog->createProduct([
            ...$data,
            'created_by' => $actor->user_id,
        ]));
    }

    /** @param array<string, mixed> $data */
    public function createPlan(array $data, int $actorId): BillingPlan
    {
        $actor = $this->platformActor($actorId);

        return DB::transaction(function () use ($data, $actor): BillingPlan {
            $product = BillingProduct::query()
                ->whereKey($data['product_id'])
                ->where('is_active', true)
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'product_id' => ['المنتج غير موجود أو غير مفعل.'],
                ]);
            }

            return $this->catalog->createPlan([
                ...$data,
                'created_by' => $actor->user_id,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, BillingPlan>|CursorPaginator<int, BillingPlan>
     */
    public function plans(array $filters = []): Collection|CursorPaginator
    {
        return $this->catalog->getPlans($filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @return CursorPaginator<int, BillingPlanVersion>
     */
    public function versionsForPlan(int $planId, array $filters = []): CursorPaginator
    {
        $plan = BillingPlan::query()->findOrFail($planId);
        $query = BillingPlanVersion::query()
            ->with([
                'plan',
                'trialPolicyVersion.policy',
                'dunningPolicyVersion.policy',
                'prices.currency',
                'features',
            ])
            ->where('plan_id', $plan->plan_id);

        if (! empty($filters['status'])) {
            $status = BillingPlanVersionStatus::tryFrom((string) $filters['status']);
            if (! $status) {
                throw ValidationException::withMessages([
                    'status' => ['حالة إصدار الخطة غير معروفة.'],
                ]);
            }
            $query->where('status', $status);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('version_number')
            ->orderByDesc('plan_version_id')
            ->cursorPaginate($perPage);
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, BillingProduct>|CursorPaginator<int, BillingProduct>
     */
    public function products(array $filters = []): Collection|CursorPaginator
    {
        $query = BillingProduct::query();

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('product_key', 'like', "%{$term}%"));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('product_id')->get();
        }

        return $query->orderBy('product_id')->cursorPaginate($perPage);
    }

    /**
     * @param array<string, mixed> $filters
     * @return CursorPaginator<int, BillingPlan>
     */
    public function publicPlansForCurrency(int $currencyId, array $filters = []): CursorPaginator
    {
        if (! $this->currencies->findActiveById($currencyId)) {
            throw ValidationException::withMessages([
                'currency_id' => ['العملة غير موجودة أو غير مفعلة.'],
            ]);
        }

        $query = BillingPlan::query()
            ->with([
                'product',
                'currentVersion.features',
                'currentVersion.prices' => fn ($prices) => $prices
                    ->where('currency_id', $currencyId)
                    ->where('is_active', true)
                    ->with('currency'),
            ])
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereHas('currentVersion', fn ($version) => $version
                ->where('status', BillingPlanVersionStatus::PUBLISHED->value))
            ->whereHas('currentVersion.prices', fn ($prices) => $prices
                ->where('currency_id', $currencyId)
                ->where('is_active', true));

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('plan_key', 'like', "%{$term}%"));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        return $query->orderBy('plan_id')->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    /** @param array<string, mixed> $policyVersions */
    public function createDraftVersion(int $planId, int $actorId, array $policyVersions = []): BillingPlanVersion
    {
        $actor = $this->platformActor($actorId);

        return DB::transaction(function () use ($planId, $actor, $policyVersions): BillingPlanVersion {
            $plan = $this->catalog->findPlan($planId, true);

            if (! $plan || ! $plan->is_active) {
                throw ValidationException::withMessages([
                    'plan_id' => ['الخطة غير موجودة أو غير مفعلة.'],
                ]);
            }

            $version = $this->catalog->createDraftVersion($planId, (int) $actor->user_id);
            if (! empty($policyVersions['trial_policy_version_id'])) {
                $policy = \App\Models\BillingTrialPolicyVersion::query()->findOrFail($policyVersions['trial_policy_version_id']);
                if ($policy->status !== 'PUBLISHED') throw ValidationException::withMessages(['trial_policy_version_id' => ['يجب اختيار سياسة تجربة منشورة.']]);
                $version->trial_policy_version_id = $policy->trial_policy_version_id;
            }
            if (! empty($policyVersions['dunning_policy_version_id'])) {
                $policy = \App\Models\BillingDunningPolicyVersion::query()->findOrFail($policyVersions['dunning_policy_version_id']);
                if ($policy->status !== 'PUBLISHED') throw ValidationException::withMessages(['dunning_policy_version_id' => ['يجب اختيار سياسة تحصيل منشورة.']]);
                $version->dunning_policy_version_id = $policy->dunning_policy_version_id;
            }
            $version->save();

            return $version->refresh()->load(['plan', 'prices.currency', 'features']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function addPrice(int $versionId, array $data, int $actorId): BillingPrice
    {
        $this->platformActor($actorId);

        return DB::transaction(function () use ($versionId, $data): BillingPrice {
            $version = $this->catalog->findVersion($versionId, true);

            if (! $version || $version->status !== BillingPlanVersionStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'plan_version_id' => ['لا يمكن تعديل أسعار نسخة منشورة أو غير موجودة.'],
                ]);
            }

            $currency = $this->currencies->findActiveById((int) $data['currency_id']);
            if (! $currency) {
                throw ValidationException::withMessages([
                    'currency_id' => ['العملة غير موجودة أو غير مفعلة.'],
                ]);
            }

            $interval = BillingInterval::tryFrom((string) $data['billing_interval']);
            if (! $interval) {
                throw ValidationException::withMessages([
                    'billing_interval' => ['فترة الفوترة غير معروفة.'],
                ]);
            }

            $intervalCount = filter_var($data['interval_count'] ?? 1, FILTER_VALIDATE_INT);
            if ($intervalCount === false || $intervalCount < 1 || $intervalCount > 120) {
                throw ValidationException::withMessages([
                    'interval_count' => ['عدد فترات الفوترة يجب أن يكون بين 1 و120.'],
                ]);
            }

            $taxBehavior = strtoupper((string) ($data['tax_behavior'] ?? 'EXCLUSIVE'));
            if (! in_array($taxBehavior, ['EXCLUSIVE', 'INCLUSIVE'], true)) {
                throw ValidationException::withMessages([
                    'tax_behavior' => ['أسلوب الضريبة يجب أن يكون EXCLUSIVE أو INCLUSIVE.'],
                ]);
            }

            $unit = Money::fromDecimal((string) $data['unit_amount'], $currency->decimal_places, $currency->code);
            $setup = Money::fromDecimal((string) ($data['setup_fee'] ?? '0'), $currency->decimal_places, $currency->code);

            if ($unit->amountMinor < 0 || $setup->amountMinor < 0) {
                throw ValidationException::withMessages([
                    'unit_amount' => ['لا يسمح بالسعر أو رسوم التفعيل السالبة.'],
                ]);
            }

            return $this->catalog->createPrice([
                'plan_version_id' => $versionId,
                'currency_id' => $currency->currency_id,
                'billing_interval' => $interval,
                'interval_count' => $intervalCount,
                'unit_amount_minor' => $unit->amountMinor,
                'setup_fee_minor' => $setup->amountMinor,
                'tax_behavior' => $taxBehavior,
                'is_active' => true,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function addFeature(int $versionId, array $data, int $actorId): BillingPlanFeature
    {
        $this->platformActor($actorId);

        return DB::transaction(function () use ($versionId, $data): BillingPlanFeature {
            $version = $this->catalog->findVersion($versionId, true);

            if (! $version || $version->status !== BillingPlanVersionStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'plan_version_id' => ['لا يمكن تعديل ميزات نسخة منشورة أو غير موجودة.'],
                ]);
            }

            $type = BillingFeatureType::tryFrom((string) $data['feature_type']);
            if (! $type) {
                throw ValidationException::withMessages([
                    'feature_type' => ['نوع الميزة غير مدعوم.'],
                ]);
            }

            $value = $this->normalizeFeatureValue($type, $data['value'] ?? null);
            $overageBehavior = strtoupper((string) ($data['overage_behavior'] ?? 'BLOCK'));

            if (! in_array($overageBehavior, ['BLOCK', 'TRACK', 'INVOICE'], true)) {
                throw ValidationException::withMessages([
                    'overage_behavior' => ['سلوك تجاوز الحصة غير مدعوم.'],
                ]);
            }

            if ($type !== BillingFeatureType::METERED && $overageBehavior !== 'BLOCK') {
                throw ValidationException::withMessages([
                    'overage_behavior' => ['سياسة تجاوز الحصة تنطبق فقط على الميزات المقاسة.'],
                ]);
            }

            $configuration = (array) ($data['configuration'] ?? []);
            if ($type === BillingFeatureType::METERED && $overageBehavior === 'INVOICE') {
                $priceCurrencyIds = $version->prices->pluck('currency_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
                /** @var array<int, array<string, mixed>> $overagePriceRows */
                $overagePriceRows = $configuration['overage_prices'] ?? [];
                $overagePrices = collect($overagePriceRows);
                $configuredCurrencyIds = $overagePrices->pluck('currency_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();

                foreach ($overagePrices as $overagePrice) {
                    $unitSize = filter_var($overagePrice['unit_size_micros'] ?? null, FILTER_VALIDATE_INT);
                    $unitAmount = filter_var($overagePrice['unit_amount_minor'] ?? null, FILTER_VALIDATE_INT);
                    if ($unitSize === false || $unitSize <= 0 || $unitAmount === false || $unitAmount < 0) {
                        throw ValidationException::withMessages([
                            'configuration' => ['تعرفة تجاوز الاستخدام يجب أن تحتوي على حجم وحدة موجب ومبلغ بوحدات العملة الصغرى غير سالب.'],
                        ]);
                    }
                }

                if ($priceCurrencyIds->all() !== $configuredCurrencyIds->all()) {
                    throw ValidationException::withMessages([
                        'configuration.overage_prices' => ['يجب تحديد تعرفة تجاوز مستقلة لكل عملة مستخدمة في أسعار نسخة الخطة، دون تكرار عملة.'],
                    ]);
                }
            }

            $feature = $this->catalog->createFeature([
                'plan_version_id' => $versionId,
                'feature_key' => trim((string) $data['feature_key']),
                'name' => trim((string) $data['name']),
                'feature_type' => $type,
                'value' => $value,
                'unit' => $data['unit'] ?? null,
                'aggregation' => strtoupper((string) ($data['aggregation'] ?? 'SUM')),
                'reset_period' => $data['reset_period'] ?? null,
                'overage_behavior' => $overageBehavior,
                'configuration' => $configuration,
            ]);

            if ($type === BillingFeatureType::METERED) {
                $meter = \App\Models\BillingUsageMeter::query()->firstOrCreate(
                    ['meter_key' => $feature->feature_key],
                    [
                        'name' => $feature->name,
                        'unit' => $feature->unit ?? 'unit',
                        'aggregation' => $feature->aggregation ?? 'SUM',
                        'reset_period' => $feature->reset_period,
                        'description' => 'عداد الاستخدام المرتبط بميزة '.$feature->feature_key,
                        'is_active' => true,
                    ]
                );

                if ($meter->unit !== ($feature->unit ?? 'unit')
                    || $meter->aggregation !== ($feature->aggregation ?? 'SUM')) {
                    throw ValidationException::withMessages([
                        'feature_key' => ['تعريف العداد مستخدم مسبقاً بوحدة أو طريقة تجميع مختلفة؛ يجب توحيد تعريف العداد عبر الخطط.'],
                    ]);
                }
            }

            return $feature;
        }, 3);
    }

    public function publishVersion(int $versionId, int $actorId): BillingPlanVersion
    {
        $actor = $this->platformActor($actorId);

        return DB::transaction(function () use ($versionId, $actor): BillingPlanVersion {
            $version = $this->catalog->findVersion($versionId, true);

            if (! $version || $version->status !== BillingPlanVersionStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'plan_version_id' => ['لا يمكن نشر هذه النسخة.'],
                ]);
            }

            if ($version->prices->isEmpty()) {
                throw ValidationException::withMessages([
                    'prices' => ['يجب إضافة سعر واحد على الأقل بعملة وفترة فوترة محددتين قبل النشر.'],
                ]);
            }

            if ($version->trial_policy_version_id) {
                $trialPolicyVersion = BillingTrialPolicyVersion::query()
                    ->find($version->trial_policy_version_id);

                if (! $trialPolicyVersion || $trialPolicyVersion->status !== 'PUBLISHED') {
                    throw ValidationException::withMessages([
                        'trial_policy_version_id' => ['يجب نشر سياسة التجربة المرتبطة بنسخة الخطة قبل نشر الخطة.'],
                    ]);
                }
            }

            if ($version->dunning_policy_version_id) {
                $dunningPolicyVersion = BillingDunningPolicyVersion::query()
                    ->find($version->dunning_policy_version_id);

                if (! $dunningPolicyVersion || $dunningPolicyVersion->status !== 'PUBLISHED') {
                    throw ValidationException::withMessages([
                        'dunning_policy_version_id' => ['يجب نشر سياسة التحصيل المرتبطة بنسخة الخطة قبل نشر الخطة.'],
                    ]);
                }
            }

            $seen = [];
            foreach ($version->prices as $price) {
                $key = $price->currency_id.':'.$price->billing_interval->value.':'.$price->interval_count;
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages([
                        'prices' => ['يوجد سعر مكرر لنفس العملة وفترة الفوترة.'],
                    ]);
                }
                $seen[$key] = true;
            }

            $plan = $this->catalog->findPlan((int) $version->plan_id, true);

            if (! $plan) {
                throw ValidationException::withMessages([
                    'plan_id' => ['الخطة الأصلية غير موجودة.'],
                ]);
            }

            $previousVersionId = $plan->current_version_id;
            if ($previousVersionId && (int) $previousVersionId !== (int) $version->plan_version_id) {
                $previousVersion = $this->catalog->findVersion((int) $previousVersionId, true);
                if ($previousVersion && $previousVersion->status === BillingPlanVersionStatus::PUBLISHED) {
                    $previousVersion->status = BillingPlanVersionStatus::ARCHIVED;
                    $previousVersion->save();
                }
            }

            $published = $this->catalog->publishVersion($version, (int) $actor->user_id);
            $this->catalog->setCurrentVersion($plan, $published->plan_version_id);

            return $published;
        }, 3);
    }

    private function platformActor(int $actorId): User
    {
        $actor = User::query()->with('role')->findOrFail($actorId);
        $this->authorization->assertPlatform($actor);

        return $actor;
    }

    private function normalizeFeatureValue(BillingFeatureType $type, mixed $value): string
    {
        if ($value === null) {
            throw ValidationException::withMessages(['value' => ['قيمة الميزة مطلوبة.']]);
        }

        if ($type === BillingFeatureType::BOOLEAN) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalized === null) {
                throw ValidationException::withMessages(['value' => ['قيمة الميزة المنطقية يجب أن تكون true أو false.']]);
            }

            return $normalized ? 'true' : 'false';
        }

        if ($type === BillingFeatureType::RESOURCE_LIMIT) {
            $limit = filter_var($value, FILTER_VALIDATE_INT);
            if ($limit === false || $limit < -1) {
                throw ValidationException::withMessages(['value' => ['حد الموارد يجب أن يكون -1 (غير محدود) أو عدداً صحيحاً غير سالب.']]);
            }

            return (string) $limit;
        }

        if ($type === BillingFeatureType::METERED) {
            $quantity = trim((string) $value);
            if ($quantity !== '-1' && ! preg_match('/^\d+(?:\.\d{1,6})?$/', $quantity)) {
                throw ValidationException::withMessages(['value' => ['حد الاستهلاك يجب أن يكون -1 أو رقماً موجباً بدقة لا تتجاوز 6 منازل.']]);
            }

            if ($quantity !== '-1') {
                $normalized = str_replace('.', '', $quantity);
                if (trim($normalized, '0') === '') {
                    throw ValidationException::withMessages(['value' => ['حد الاستهلاك يجب أن يكون أكبر من صفر أو -1.']]);
                }
            }

            return $quantity;
        }

        if ($type === BillingFeatureType::JSON) {
            $encoded = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
            json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

            return $encoded;
        }

        return trim((string) $value);
    }
}
