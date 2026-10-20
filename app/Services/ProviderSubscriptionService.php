<?php

namespace App\Services;

use App\Interfaces\FeatureRepositoryInterface;
use App\Interfaces\ProviderSubscriptionRepositoryInterface;
use App\Interfaces\SubscriptionPlanFeatureConsumptionRepositoryInterface;
use App\Interfaces\SubscriptionPlanFeatureRepositoryInterface;
use App\Models\ProviderSubscription;
use App\Models\SubscriptionPlanFeatureConsumption;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة اشتراكات المزودين ومحرك استهلاك الحصص والميزات (Provider Subscription & Quota Engine).
 */
class ProviderSubscriptionService
{
    public function __construct(
        protected ProviderSubscriptionRepositoryInterface $providerSubscriptionRepository,
        protected SubscriptionPlanFeatureConsumptionRepositoryInterface $consumptionRepository,
        protected SubscriptionPlanFeatureRepositoryInterface $planFeatureRepository,
        protected FeatureRepositoryInterface $featureRepository
    ) {}

    /**
     * جلب قائمة الاشتراكات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->providerSubscriptionRepository->getFiltered($filters);
    }

    /**
     * جلب الاشتراكات المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->providerSubscriptionRepository->getTrashedFiltered($filters);
    }

    /**
     * جلب تفاصيل اشتراك محدد عبر معرفه.
     */
    public function getById(int $id): ProviderSubscription
    {
        /** @var ProviderSubscription|null $subscription */
        $subscription = $this->providerSubscriptionRepository->findById($id);
        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription_id' => ["الاشتراك برقم [{$id}] غير موجود."],
            ]);
        }

        $subscription->load([
            'network',
            'plan.planFeatures.feature',
            'createdBy',
            'consumptions.planFeature.feature',
        ]);

        return $subscription;
    }

    /**
     * جلب الاشتراك الساري والنشط حالياً لشبكة مزود معينة مع تفاصيل الباقة والميزات.
     * مع الترقية والتفعيل التلقائي للاشتراك المجدول التالي عند انتهاء صلاحية الحالي.
     */
    public function getActiveForNetwork(int $networkId): ?ProviderSubscription
    {
        $active = $this->providerSubscriptionRepository->getActiveForNetwork($networkId);
        if ($active && $active->isActive()) {
            return $active;
        }

        // إذا انتهى الاشتراك النشط ووجد اشتراك مجدول في طابور الانتظار، يتم تفعيله تلقائياً
        $queued = $this->providerSubscriptionRepository->getQueuedForNetwork($networkId);
        if ($queued) {
            return DB::transaction(function () use ($networkId, $queued) {
                $this->providerSubscriptionRepository->expireExistingForNetwork($networkId);

                return $this->providerSubscriptionRepository->activateQueuedSubscription($queued->subscription_id);
            });
        }

        return $active;
    }

    /**
     * التحقق مما إذا كان مسموحاً للشبكة باستهلاك ميزة معينة وفقاً لحصص باقتها النشطة،
     * أو إمكانية استهلاكها عبر التفعيل المبكر التلقائي للخطة المجدولة التالية (المسار الأول).
     */
    public function canConsume(int $networkId, string $featureName, int $amount = 1): bool
    {
        $subscription = $this->getActiveForNetwork($networkId);
        if (! $subscription || ! $subscription->isActive()) {
            return false;
        }

        $feature = $this->featureRepository->findByName($featureName);
        if (! $feature) {
            return true; // الميزة غير مقيدة بكتالوج النظام
        }

        $planFeature = $this->planFeatureRepository->findByPlanAndFeature($subscription->plan_id, $feature->feature_id);
        if ($planFeature && $planFeature->status) {
            if ($planFeature->isUnlimited()) {
                return true;
            }

            $quotaLimit = (int) $planFeature->plan_feature_value;
            $consumption = $this->consumptionRepository->findBySubscriptionAndFeature(
                $subscription->subscription_id,
                $planFeature->subscription_plan_feature_id
            );

            $currentConsumed = $consumption ? (int) $consumption->consumption_value : 0;
            if (($currentConsumed + $amount) <= $quotaLimit) {
                return true;
            }
        }

        // المسار الأول: إذا نفدت الحصة في الخطة الأولى، نتحقق من وجود خطة مجدولة قادمة
        $queuedSub = $this->providerSubscriptionRepository->getQueuedForNetwork($networkId);
        if ($queuedSub) {
            $nextPlanFeature = $this->planFeatureRepository->findByPlanAndFeature($queuedSub->plan_id, $feature->feature_id);
            if ($nextPlanFeature && $nextPlanFeature->status) {
                if ($nextPlanFeature->isUnlimited()) {
                    return true;
                }

                return (int) $nextPlanFeature->plan_feature_value >= $amount;
            }
        }

        return false;
    }

    /**
     * تسجيل واحتساب استهلاك ميزة للشبكة مع التحقق من الحصة بالقفل المتشائم.
     * مع التفعيل التلقائي المبكر للخطة التالية عند نفاد حصة الميزة (المسار الأول).
     */
    public function consume(int $networkId, string $featureName, int $amount = 1): SubscriptionPlanFeatureConsumption
    {
        return DB::transaction(function () use ($networkId, $featureName, $amount) {
            $subscription = $this->getActiveForNetwork($networkId);
            if (! $subscription || ! $subscription->isActive()) {
                throw ValidationException::withMessages([
                    'subscription' => ['لا يوجد اشتراك سار ونشط لهذه الشبكة.'],
                ]);
            }

            $feature = $this->featureRepository->findByName($featureName);
            if (! $feature) {
                throw ValidationException::withMessages([
                    'feature_name' => ["الميزة المطلوبة [{$featureName}] غير مسجلة في النظام."],
                ]);
            }

            $planFeature = $this->planFeatureRepository->findByPlanAndFeature($subscription->plan_id, $feature->feature_id);

            $quotaExceeded = false;
            $quotaLimit = 0;

            if (! $planFeature || ! $planFeature->status) {
                $quotaExceeded = true;
            } elseif (! $planFeature->isUnlimited()) {
                $quotaLimit = (int) $planFeature->plan_feature_value;
                $consumption = $this->consumptionRepository->findBySubscriptionAndFeature(
                    $subscription->subscription_id,
                    $planFeature->subscription_plan_feature_id
                );

                $currentConsumed = $consumption ? (int) $consumption->consumption_value : 0;
                if (($currentConsumed + $amount) > $quotaLimit) {
                    $quotaExceeded = true;
                }
            }

            // تطبيق المسار الأول: عند نفاد ميزة معينة، تفعيل الخطة التالية المحجوزة مبكراً فوراً
            if ($quotaExceeded) {
                $queuedSub = $this->providerSubscriptionRepository->getQueuedForNetwork($networkId);
                if ($queuedSub) {
                    // 1. إنهاء الاشتراك الحالي
                    $this->providerSubscriptionRepository->expireExistingForNetwork($networkId);

                    // 2. تفعيل الاشتراك المجدول فوراً مع إعادة ضبط تواريخ الصلاحية من اللحظة الحالية
                    $subscription = $this->providerSubscriptionRepository->activateQueuedSubscription($queuedSub->subscription_id);

                    // 3. قراءة ميزة الخطة الجديدة
                    $planFeature = $this->planFeatureRepository->findByPlanAndFeature($subscription->plan_id, $feature->feature_id);
                    if (! $planFeature || ! $planFeature->status) {
                        throw ValidationException::withMessages([
                            'feature' => ["الميزة [{$featureName}] غير مشمولة في باقة اشتراكك الجديدة المفعّلة مبكراً."],
                        ]);
                    }

                    if (! $planFeature->isUnlimited()) {
                        $newQuotaLimit = (int) $planFeature->plan_feature_value;
                        $newConsumption = $this->consumptionRepository->findBySubscriptionAndFeature(
                            $subscription->subscription_id,
                            $planFeature->subscription_plan_feature_id
                        );

                        $newConsumed = $newConsumption ? (int) $newConsumption->consumption_value : 0;
                        if (($newConsumed + $amount) > $newQuotaLimit) {
                            throw ValidationException::withMessages([
                                'quota' => ["تم تجاوز الحد الأقصى للميزة [{$featureName}] حتى في الباقة الجديدة المفعّلة مبكراً ({$newQuotaLimit})."],
                            ]);
                        }
                    }
                } else {
                    $limitInfo = $quotaLimit > 0 ? " ({$quotaLimit})" : '';
                    throw ValidationException::withMessages([
                        'quota' => ["تم تجاوز الحد الأقصى المسموح به في باقتك الحالية للميزة [{$featureName}]{$limitInfo}، ولا توجد باقة اشتراك مجدولة لتفعيلها مبكراً."],
                    ]);
                }
            }

            return $this->consumptionRepository->recordConsumption(
                $subscription->subscription_id,
                $planFeature->subscription_plan_feature_id,
                $amount
            );
        });
    }

    /**
     * تفعيل الاشتراك المجدول مبكراً يدوياً للشبكة بطلب مباشر.
     */
    public function activateEarlyForNetwork(int $networkId): ProviderSubscription
    {
        return DB::transaction(function () use ($networkId) {
            $queued = $this->providerSubscriptionRepository->getQueuedForNetwork($networkId);
            if (! $queued) {
                throw ValidationException::withMessages([
                    'subscription' => ['لا توجد خطة اشتراك مجدولة في قائمة الانتظار لهذه الشبكة.'],
                ]);
            }

            $this->providerSubscriptionRepository->expireExistingForNetwork($networkId);

            return $this->providerSubscriptionRepository->activateQueuedSubscription($queued->subscription_id);
        });
    }

    /**
     * تمديد فترة صلاحية اشتراك قائم لشبكة معينة.
     */
    public function extend(int $subscriptionId, int $additionalDays): ProviderSubscription
    {
        $this->getById($subscriptionId);

        return $this->providerSubscriptionRepository->extendSubscription($subscriptionId, $additionalDays);
    }

    /**
     * جلب سجلات استهلاك الميزات لاشتراك محدد.
     */
    public function getConsumptions(int $subscriptionId): Collection
    {
        $this->getById($subscriptionId);

        return $this->consumptionRepository->getBySubscriptionId($subscriptionId);
    }
}
