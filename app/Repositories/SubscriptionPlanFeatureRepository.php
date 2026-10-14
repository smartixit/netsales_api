<?php

namespace App\Repositories;

use App\Interfaces\SubscriptionPlanFeatureRepositoryInterface;
use App\Models\SubscriptionPlanFeature;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة ميزات باقات الاشتراك وحصصها (Subscription Plan Feature Repository).
 *
 * @property SubscriptionPlanFeature $model
 */
class SubscriptionPlanFeatureRepository extends BaseRepository implements SubscriptionPlanFeatureRepositoryInterface
{
    public function __construct(SubscriptionPlanFeature $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب ميزات باقة معينة مع تفاصيل الميزة الأساسية.
     */
    public function getByPlanId(int $planId): Collection
    {
        return $this->model->newQuery()
            ->with(['feature'])
            ->where('plan_id', $planId)
            ->get();
    }

    /**
     * البحث عن ربط ميزة محددة بباقة معينة بالقفل المتشائم.
     */
    public function findByPlanAndFeature(int $planId, int $featureId): ?SubscriptionPlanFeature
    {
        return $this->model->newQuery()
            ->with(['feature'])
            ->where('plan_id', $planId)
            ->where('feature_id', $featureId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * حذف كافة ميزات باقة معينة.
     */
    public function deleteByPlanId(int $planId): int
    {
        $features = $this->model->newQuery()->where('plan_id', $planId)->get();
        $count = 0;
        foreach ($features as $feature) {
            $feature->delete();
            $this->recordDeletion($feature);
            $count++;
        }

        return $count;
    }
}
