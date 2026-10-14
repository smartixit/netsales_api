<?php

namespace App\Interfaces;

use App\Models\ProviderSubscription;

/**
 * واجهة مستودع اشتراكات مزودي الشبكات (Provider Subscription Repository Interface).
 */
interface ProviderSubscriptionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب الاشتراك الساري والنشط حالياً لشبكة معينة مع باقته وميزاتها.
     */
    public function getActiveForNetwork(int $networkId): ?ProviderSubscription;

    /**
     * إنهاء أو إلغاء تفعيل الاشتراكات السابقة لشبكة معينة عند اعتماد اشتراك جديد.
     */
    public function expireExistingForNetwork(int $networkId): int;

    /**
     * تمديد فترة صلاحية اشتراك قائم بعدد محدد من الأيام.
     */
    public function extendSubscription(int $subscriptionId, int $additionalDays): ProviderSubscription;

    /**
     * جلب أول اشتراك مجدول في طابور الانتظار للشبكة.
     */
    public function getQueuedForNetwork(int $networkId): ?ProviderSubscription;

    /**
     * تفعيل اشتراك مجدول مبكراً وتحديث تواريخه بالقفل المتشائم.
     */
    public function activateQueuedSubscription(int $queuedSubscriptionId): ProviderSubscription;
}
