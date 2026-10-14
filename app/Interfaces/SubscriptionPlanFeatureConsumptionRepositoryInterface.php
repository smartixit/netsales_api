<?php

namespace App\Interfaces;

use App\Models\SubscriptionPlanFeatureConsumption;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع تتبع استهلاك ميزات وحصص الاشتراكات (Feature Consumption Repository Interface).
 */
interface SubscriptionPlanFeatureConsumptionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب كافة سجلات الاستهلاك لاشتراك معين مع بيانات الميزة.
     */
    public function getBySubscriptionId(int $subscriptionId): Collection;

    /**
     * البحث عن سجل استهلاك ميزة محددة لاشتراك معين بالقفل المتشائم.
     */
    public function findBySubscriptionAndFeature(int $subscriptionId, int $planFeatureId): ?SubscriptionPlanFeatureConsumption;

    /**
     * تسجيل استهلاك من حصة الميزة للاشتراك.
     */
    public function recordConsumption(int $subscriptionId, int $planFeatureId, int $amount = 1): SubscriptionPlanFeatureConsumption;

    /**
     * تهيئة سجلات الاستهلاك بالصفر لكافة ميزات الباقة عند تفعيل الاشتراك.
     */
    public function initializeForSubscription(int $subscriptionId, array $planFeatureIds): void;
}
