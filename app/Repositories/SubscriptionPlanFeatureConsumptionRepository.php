<?php

namespace App\Repositories;

use App\Interfaces\SubscriptionPlanFeatureConsumptionRepositoryInterface;
use App\Models\SubscriptionPlanFeatureConsumption;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع تتبع استهلاك ميزات وحصص الاشتراكات (Feature Consumption Repository).
 *
 * @property SubscriptionPlanFeatureConsumption $model
 */
class SubscriptionPlanFeatureConsumptionRepository extends BaseRepository implements SubscriptionPlanFeatureConsumptionRepositoryInterface
{
    public function __construct(SubscriptionPlanFeatureConsumption $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب سجلات الاستهلاك لاشتراك معين.
     */
    public function getBySubscriptionId(int $subscriptionId): Collection
    {
        return $this->model->newQuery()
            ->with(['planFeature.feature'])
            ->forSubscription($subscriptionId)
            ->get();
    }

    /**
     * البحث عن سجل استهلاك ميزة محددة لاشتراك معين بالقفل المتشائم.
     */
    public function findBySubscriptionAndFeature(int $subscriptionId, int $planFeatureId): ?SubscriptionPlanFeatureConsumption
    {
        return $this->model->newQuery()
            ->forSubscription($subscriptionId)
            ->forFeature($planFeatureId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * تسجيل استهلاك من حصة الميزة للاشتراك بالقفل المتشائم.
     */
    public function recordConsumption(int $subscriptionId, int $planFeatureId, int $amount = 1): SubscriptionPlanFeatureConsumption
    {
        /** @var SubscriptionPlanFeatureConsumption $consumption */
        $consumption = $this->model->newQuery()
            ->firstOrCreate(
                [
                    'subscription_id' => $subscriptionId,
                    'feature_id' => $planFeatureId,
                ],
                [
                    'consumption_value' => 0,
                ]
            );

        $consumption->recordConsumption($amount);

        return $consumption;
    }

    /**
     * تهيئة سجلات الاستهلاك بالصفر لكافة ميزات الباقة عند تفعيل الاشتراك.
     */
    public function initializeForSubscription(int $subscriptionId, array $planFeatureIds): void
    {
        $now = now('Asia/Aden');
        $records = [];
        foreach ($planFeatureIds as $planFeatureId) {
            $records[] = [
                'subscription_id' => $subscriptionId,
                'feature_id' => (int) $planFeatureId,
                'consumption_value' => 0,
                'last_consumption_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($records)) {
            $this->model->newQuery()->insert($records);
        }
    }
}
