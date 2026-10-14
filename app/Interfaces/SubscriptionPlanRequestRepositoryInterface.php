<?php

namespace App\Interfaces;

use App\Enums\RequestStatus;
use App\Models\SubscriptionPlanRequest;

/**
 * واجهة مستودع طلبات اشتراك باقات المزودين (Subscription Plan Request Repository Interface).
 */
interface SubscriptionPlanRequestRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * البحث عن طلب اشتراك عبر المعرف الفريد للأعمال (request_uuid).
     */
    public function findByUuid(string $uuid): ?SubscriptionPlanRequest;

    /**
     * التحقق مما إذا كانت الشبكة تمتلك طلب اشتراك معلقاً حالياً.
     */
    public function hasPendingRequest(int $networkId): bool;

    /**
     * تحديث حالة طلب الاشتراك مع بيانات المراجع والسبب بالقفل المتشائم.
     */
    public function updateStatus(
        int $id,
        RequestStatus $status,
        int $reviewerId,
        ?string $reason = null,
        ?string $note = null
    ): SubscriptionPlanRequest;
}
