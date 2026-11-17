<?php

namespace App\Repositories;

use App\Enums\RequestStatus;
use App\Interfaces\RequestRepositoryInterface;
use App\Models\Request as RequestModel;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
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
     * تطبيق الفلاتر الخاصة بطلبات الكروت.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (! empty($filters['network_ids'])) {
            $query->whereIn('network_id', (array) $filters['network_ids']);
        }

        if (! empty($filters['pos_ids'])) {
            $query->whereIn('pos_id', (array) $filters['pos_ids']);
        }

        if (! empty($filters['status'])) {
            $query->status($filters['status']);
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
                    ->when(ctype_digit((string) $search), fn ($query) => $query->orWhere('request_id', (int) $search))
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
    }

    /**
     * جلب قائمة الطلبات مع الفلترة المتقدمة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos', 'createdBy', 'reviewedBy', 'items.voucherPackage']): Collection|CursorPaginator
    {
        return parent::getFiltered($filters, $relations);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{total:int,pending:int,approved:int,rejected:int,cancelled:int}
     */
    public function getStatusSummary(array $filters = []): array
    {
        unset($filters['status'], $filters['per_page'], $filters['cursor'], $filters['all']);
        $query = $this->model->newQuery();
        $this->applyFilters($query, $filters);
        $row = $query
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [RequestStatus::PENDING->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS approved', [RequestStatus::APPROVED->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS rejected', [RequestStatus::REJECTED->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [RequestStatus::CANCELLED->value])
            ->first();
        $values = $row?->getAttributes() ?? [];
        return [
            'total' => (int) ($values['total'] ?? 0),
            'pending' => (int) ($values['pending'] ?? 0),
            'approved' => (int) ($values['approved'] ?? 0),
            'rejected' => (int) ($values['rejected'] ?? 0),
            'cancelled' => (int) ($values['cancelled'] ?? 0),
        ];
    }

    /**
     * جلب الطلبات المحذوفة مؤقتاً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator
    {
        return parent::getTrashedFiltered($filters, $relations);
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
