<?php

namespace App\Repositories;

use App\Enums\RequestStatus;
use App\Interfaces\RequestRepositoryInterface;
use App\Models\Request as RequestModel;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة طلبات الكروت (Enterprise Request Repository).
 *
 * @property RequestModel $model
 */
class RequestRepository extends BaseRepository implements RequestRepositoryInterface
{
    public function __construct(RequestModel $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة الطلبات مع الفلترة المتقدمة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos', 'createdBy', 'reviewedBy', 'items.voucherPackage']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (! empty($filters['pos_id'])) {
            $query->forPos((int) $filters['pos_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['reviewed_by'])) {
            $query->where('reviewed_by', (int) $filters['reviewed_by']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('request_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('request_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('request_uuid', 'like', "%{$search}%")
                    ->orWhere('request_note', 'like', "%{$search}%")
                    ->orWhereHas('network', function ($nq) use ($search) {
                        $nq->where('network_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('pos', function ($pq) use ($search) {
                        $pq->where('pos_name', 'like', "%{$search}%")
                            ->orWhere('pos_phone_number', 'like', "%{$search}%");
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
     * جلب الطلبات المحذوفة مؤقتاً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->onlyTrashed()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (! empty($filters['pos_id'])) {
            $query->forPos((int) $filters['pos_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('request_uuid', 'like', "%{$search}%")
                    ->orWhereHas('network', function ($nq) use ($search) {
                        $nq->where('network_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('pos', function ($pq) use ($search) {
                        $pq->where('pos_name', 'like', "%{$search}%");
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
     * البحث عن طلب عبر معرف UUID مع خيار القفل المتشائم.
     */
    public function findByUuid(string $uuid, bool $lock = false): ?RequestModel
    {
        $query = $this->model->newQuery()->where('request_uuid', $uuid);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * تحديث حالة الطلب مع توثيق المراجع والتاريخ والملاحظة.
     */
    public function updateStatus(int $requestId, RequestStatus $status, ?int $reviewedBy = null, ?string $note = null): bool
    {
        $data = [
            'status' => $status,
            'review_date' => now('Asia/Aden'),
        ];

        if ($reviewedBy !== null) {
            $data['reviewed_by'] = $reviewedBy;
        }

        if ($note !== null) {
            $data['request_note'] = $note;
        }

        return (bool) $this->patch($requestId, $data);
    }
}
