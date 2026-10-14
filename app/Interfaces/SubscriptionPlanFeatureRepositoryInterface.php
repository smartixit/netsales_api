<?php

namespace App\Interfaces;

use App\Models\SubscriptionPlanFeature;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع ميزات باقات الاشتراك وحصصها (Subscription Plan Feature Repository Interface).
 */
interface SubscriptionPlanFeatureRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب ميزات باقة معينة مع بيانات الميزة الأساسية.
     */
    public function getByPlanId(int $planId): Collection;

    /**
     * البحث عن ربط ميزة محددة بباقة معينة.
     */
    public function findByPlanAndFeature(int $planId, int $featureId): ?SubscriptionPlanFeature;

    /**
     * حذف جميع ميزات باقة معينة.
     */
    public function deleteByPlanId(int $planId): int;
}
