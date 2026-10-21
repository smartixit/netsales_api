<?php

namespace App\Repositories;

use App\Interfaces\SubscriptionPlanRepositoryInterface;
use App\Models\SubscriptionPlan;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة باقات الاشتراك (Enterprise Subscription Plan Repository).
 *
 * @property SubscriptionPlan $model
 */
class SubscriptionPlanRepository extends BaseRepository implements SubscriptionPlanRepositoryInterface
{
    public function __construct(SubscriptionPlan $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة باقات الاشتراك مع الفلترة والبحث والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['features', 'planFeatures']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $status ? $query->active() : $query->inactive();
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('plan_name', 'like', "%{$search}%")
                    ->orWhere('plan_uuid', 'like', "%{$search}%")
                    ->orWhere('plan_desc', 'like', "%{$search}%");
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('plan_id', 'desc')->get();
        }

        return $query->orderBy('plan_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن باقة عبر المعرف الفريد للأعمال.
     */
    public function findByUuid(string $uuid): ?SubscriptionPlan
    {
        return $this->model->newQuery()
            ->with(['features', 'planFeatures.feature'])
            ->where('plan_uuid', $uuid)
            ->first();
    }

    /**
     * تبديل حالة تفعيل الباقة بالقفل المتشائم.
     */
    public function toggleStatus(int $id): bool
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->model->newQuery()->where('plan_id', $id)->lockForUpdate()->first();
        if (! $plan) {
            return false;
        }

        $oldStatus = $plan->status;
        $newStatus = ! $oldStatus;
        $plan->status = $newStatus;
        $plan->save();

        $this->recordModification($plan, ['status' => $oldStatus]);

        return $newStatus;
    }

    /**
     * جلب كافة الباقات المفعّلة للاشتراك.
     */
    public function getActivePlans(array $relations = ['features', 'planFeatures.feature']): Collection
    {
        return $this->model->newQuery()
            ->with($relations)
            ->active()
            ->orderBy('plan_price', 'asc')
            ->get();
    }
}
