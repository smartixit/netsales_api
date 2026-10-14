<?php

namespace App\Interfaces;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع باقات الاشتراك (Subscription Plan Repository Interface).
 */
interface SubscriptionPlanRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * البحث عن باقة عبر المعرف الفريد للأعمال (plan_uuid).
     */
    public function findByUuid(string $uuid): ?SubscriptionPlan;

    /**
     * تبديل حالة تفعيل الباقة (نشط / معطل).
     */
    public function toggleStatus(int $id): bool;

    /**
     * جلب كافة الباقات المفعلة للاشتراك مع علاقاتها.
     */
    public function getActivePlans(array $relations = ['features', 'planFeatures']): Collection;
}
