<?php

namespace App\Repositories;

use App\Interfaces\NetworkPosRepositoryInterface;
use App\Models\NetworkPos;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة ارتباط نقاط البيع بمزودي الشبكات (Enterprise Network POS Repository).
 *
 * @property NetworkPos $model
 */
class NetworkPosRepository extends BaseRepository implements NetworkPosRepositoryInterface
{
    public function __construct(NetworkPos $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة الارتباطات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (! empty($filters['pos_id'])) {
            $query->where('pos_id', (int) $filters['pos_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('network', function ($nq) use ($search) {
                    $nq->where('network_name', 'like', "%{$search}%");
                })->orWhereHas('pos', function ($pq) use ($search) {
                    $pq->where('pos_name', 'like', "%{$search}%")
                        ->orWhere('pos_phone_number', 'like', "%{$search}%");
                });
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('network_pos_id', 'desc')->get();
        }

        return $query->orderBy('network_pos_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن ارتباط محدد بين شبكة ونقطة بيع.
     */
    public function findByNetworkAndPos(int $networkId, int $posId): ?NetworkPos
    {
        return $this->model->newQuery()
            ->where('network_id', $networkId)
            ->where('pos_id', $posId)
            ->first();
    }

    /**
     * جلب نقاط البيع المرتبطة بشبكة محددة.
     */
    public function getPosForNetwork(int $networkId, array $filters = []): Collection|CursorPaginator
    {
        $filters['network_id'] = $networkId;

        return $this->getFiltered($filters, ['pos']);
    }

    /**
     * جلب الشبكات المرتبطة بنقطة بيع محددة.
     */
    public function getNetworksForPos(int $posId, array $filters = []): Collection|CursorPaginator
    {
        $filters['pos_id'] = $posId;

        return $this->getFiltered($filters, ['network']);
    }

    /**
     * تغيير حالة مجموعة ارتباطات مجمعة (تفعيل أو تعطيل).
     */
    public function bulkToggleStatus(array $ids, bool $status): int
    {
        if (empty($ids)) {
            return 0;
        }

        // قفل السجلات للتعديل الآمن
        $this->model->newQuery()->whereIn('network_pos_id', $ids)->lockForUpdate()->get();

        $count = $this->model->newQuery()
            ->whereIn('network_pos_id', $ids)
            ->update(['status' => $status]);

        if ($count > 0) {
            $this->recordModification(
                $this->model,
                ['status' => ! $status],
                ['status' => $status, 'affected_ids' => $ids]
            );
        }

        return $count;
    }
}
