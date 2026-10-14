<?php

namespace App\Repositories;

use App\Enums\ProviderSubscriptionStatus;
use App\Interfaces\ProviderSubscriptionRepositoryInterface;
use App\Models\ProviderSubscription;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * مستودع إدارة اشتراكات مزودي الشبكات (Provider Subscription Repository).
 *
 * @property ProviderSubscription $model
 */
class ProviderSubscriptionRepository extends BaseRepository implements ProviderSubscriptionRepositoryInterface
{
    public function __construct(ProviderSubscription $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة الاشتراكات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(
        array $filters = [],
        array $relations = ['network', 'plan.planFeatures.feature', 'createdBy', 'consumptions.planFeature.feature']
    ): Collection|CursorPaginator {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', (int) $filters['plan_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['active_only'])) {
            $query->where('status', ProviderSubscriptionStatus::ACTIVE)
                ->where(function ($q) {
                    $q->whereNull('subscription_end_date')
                        ->orWhere('subscription_end_date', '>', now('Asia/Aden'));
                });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('subscription_id', 'desc')->get();
        }

        return $query->orderBy('subscription_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب الاشتراك الساري والنشط حالياً لشبكة معينة.
     */
    public function getActiveForNetwork(int $networkId): ?ProviderSubscription
    {
        return $this->model->newQuery()
            ->with(['plan.planFeatures.feature', 'consumptions.planFeature.feature'])
            ->where('network_id', $networkId)
            ->where('status', ProviderSubscriptionStatus::ACTIVE)
            ->where(function ($q) {
                $q->whereNull('subscription_end_date')
                    ->orWhere('subscription_end_date', '>', now('Asia/Aden'));
            })
            ->orderBy('subscription_id', 'desc')
            ->first();
    }

    /**
     * إنهاء الاشتراكات السابقة لشبكة معينة بالقفل المتشائم.
     */
    public function expireExistingForNetwork(int $networkId): int
    {
        $existing = $this->model->newQuery()
            ->where('network_id', $networkId)
            ->where('status', ProviderSubscriptionStatus::ACTIVE)
            ->lockForUpdate()
            ->get();

        $count = 0;
        foreach ($existing as $sub) {
            $oldStatus = $sub->status;
            $sub->status = ProviderSubscriptionStatus::EXPIRED;
            $sub->save();
            $this->recordModification($sub, ['status' => $oldStatus]);
            $count++;
        }

        return $count;
    }

    /**
     * تمديد فترة صلاحية اشتراك قائم بالقفل المتشائم.
     */
    public function extendSubscription(int $subscriptionId, int $additionalDays): ProviderSubscription
    {
        /** @var ProviderSubscription|null $sub */
        $sub = $this->model->newQuery()->where('subscription_id', $subscriptionId)->lockForUpdate()->first();
        if (! $sub) {
            throw new RuntimeException("الاشتراك برقم [{$subscriptionId}] غير موجود.");
        }

        $oldEndDate = $sub->subscription_end_date;
        $currentEnd = $sub->subscription_end_date ? Carbon::parse($sub->subscription_end_date) : now('Asia/Aden');
        // إذا كان الاشتراك منتهياً، يتم التمديد بدءاً من اليوم
        if ($currentEnd->isPast()) {
            $currentEnd = now('Asia/Aden');
        }

        $sub->subscription_end_date = $currentEnd->addDays($additionalDays);
        $sub->status = ProviderSubscriptionStatus::ACTIVE;
        $sub->save();

        $this->recordModification($sub, ['subscription_end_date' => $oldEndDate]);

        return $sub;
    }

    /**
     * جلب أول اشتراك مجدول في طابور الانتظار للشبكة.
     */
    public function getQueuedForNetwork(int $networkId): ?ProviderSubscription
    {
        return $this->model->newQuery()
            ->with(['plan.planFeatures.feature', 'consumptions.planFeature.feature'])
            ->where('network_id', $networkId)
            ->where('status', ProviderSubscriptionStatus::QUEUED)
            ->orderBy('subscription_id', 'asc')
            ->first();
    }

    /**
     * تفعيل اشتراك مجدول مبكراً وتحديث تواريخه بالقفل المتشائم.
     */
    public function activateQueuedSubscription(int $queuedSubscriptionId): ProviderSubscription
    {
        /** @var ProviderSubscription|null $sub */
        $sub = $this->model->newQuery()
            ->with(['plan'])
            ->where('subscription_id', $queuedSubscriptionId)
            ->lockForUpdate()
            ->first();

        if (! $sub) {
            throw new RuntimeException("الاشتراك المجدول برقم [{$queuedSubscriptionId}] غير موجود.");
        }

        $oldStatus = $sub->status;
        $planValidity = (int) ($sub->plan?->plan_validity ?? 30);

        $startDate = now('Asia/Aden');
        $endDate = (clone $startDate)->addDays($planValidity);

        $sub->status = ProviderSubscriptionStatus::ACTIVE;
        $sub->subscription_start_date = $startDate;
        $sub->subscription_end_date = $endDate;
        $sub->save();

        $this->recordModification($sub, ['status' => $oldStatus]);

        return $sub;
    }
}
