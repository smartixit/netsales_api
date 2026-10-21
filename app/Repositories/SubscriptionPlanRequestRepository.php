<?php

namespace App\Repositories;

use App\Enums\RequestStatus;
use App\Interfaces\SubscriptionPlanRequestRepositoryInterface;
use App\Models\SubscriptionPlanRequest;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * مستودع طلبات اشتراك باقات المزودين (Enterprise Subscription Plan Request Repository).
 *
 * @property SubscriptionPlanRequest $model
 */
class SubscriptionPlanRequestRepository extends BaseRepository implements SubscriptionPlanRequestRepositoryInterface
{
    public function __construct(SubscriptionPlanRequest $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب طلبات الاشتراك مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(
        array $filters = [],
        array $relations = ['network', 'plan', 'requestedBy', 'reviewedBy', 'bankAccount']
    ): Collection|CursorPaginator {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', (int) $filters['plan_id']);
        }

        if (! empty($filters['status'])) {
            $query->status($filters['status']);
        }

        if (! empty($filters['request_payment_type'])) {
            $query->where('request_payment_type', $filters['request_payment_type']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('request_uuid', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
                    ->orWhereHas('network', function ($nq) use ($search) {
                        $nq->where('network_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('plan', function ($pq) use ($search) {
                        $pq->where('plan_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('request_id', 'desc')->get();
        }

        return $query->orderBy('request_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن طلب اشتراك عبر المعرف الفريد للأعمال.
     */
    public function findByUuid(string $uuid): ?SubscriptionPlanRequest
    {
        return $this->model->newQuery()
            ->with(['network', 'plan.planFeatures.feature', 'requestedBy', 'reviewedBy', 'bankAccount'])
            ->where('request_uuid', $uuid)
            ->first();
    }

    /**
     * التحقق مما إذا كانت الشبكة تمتلك طلباً معلقاً حالياً.
     */
    public function hasPendingRequest(int $networkId): bool
    {
        return $this->model->newQuery()
            ->forNetwork($networkId)
            ->pending()
            ->exists();
    }

    /**
     * تحديث حالة طلب الاشتراك بالقفل المتشائم.
     */
    public function updateStatus(
        int $id,
        RequestStatus $status,
        int $reviewerId,
        ?string $reason = null,
        ?string $note = null
    ): SubscriptionPlanRequest {
        /** @var SubscriptionPlanRequest|null $request */
        $request = $this->model->newQuery()->where('request_id', $id)->lockForUpdate()->first();
        if (! $request) {
            throw new RuntimeException("طلب الاشتراك برقم [{$id}] غير موجود.");
        }

        $oldStatus = $request->status;
        $request->status = $status;
        $request->reviewed_by = $reviewerId;
        $request->review_date = now('Asia/Aden');
        if ($reason !== null) {
            $request->rejection_reason = $reason;
        }
        if ($note !== null) {
            $request->request_note = $note;
        }
        $request->save();

        $this->recordModification($request, ['status' => $oldStatus]);

        return $request;
    }
}
