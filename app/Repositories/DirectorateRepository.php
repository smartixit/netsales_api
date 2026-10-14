<?php

namespace App\Repositories;

use App\Interfaces\DirectorateRepositoryInterface;
use App\Models\Directorate;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة المديريات (Directorate Repository).
 *
 * @property Directorate $model
 */
class DirectorateRepository extends BaseRepository implements DirectorateRepositoryInterface
{
    public function __construct(Directorate $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة المديريات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['governorate']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($filters['governorate_id'])) {
            $query->inGovernorate((int) $filters['governorate_id']);
        }

        if (! empty($filters['search'])) {
            $query->search(trim($filters['search']));
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('governorate_id')->orderBy('directorate_name')->get();
        }

        return $query->orderBy('directorate_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب المديريات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['governorate']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->onlyTrashed();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($filters['governorate_id'])) {
            $query->inGovernorate((int) $filters['governorate_id']);
        }

        if (! empty($filters['search'])) {
            $query->search(trim($filters['search']));
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('directorate_id', 'desc')->get();
        }

        return $query->orderBy('directorate_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب كافة مديريات محافظة محددة.
     */
    public function getByGovernorate(int $governorateId): Collection
    {
        return $this->model->newQuery()
            ->inGovernorate($governorateId)
            ->withNetworksCount()
            ->withPosCount()
            ->orderBy('directorate_name')
            ->get();
    }

    /**
     * البحث عن مديرية باسمها ضمن محافظة محددة.
     */
    public function findByNameAndGovernorate(string $name, int $governorateId): ?Directorate
    {
        return $this->model->newQuery()
            ->where('governorate_id', $governorateId)
            ->where('directorate_name', $name)
            ->first();
    }

    /**
     * الحذف المجمع للمديريات ناعماً مع التدقيق.
     */
    public function bulkDelete(array $ids): int
    {
        $records = $this->model->newQuery()->whereIn('directorate_id', $ids)->get();
        $count = 0;

        foreach ($records as $record) {
            if ($record->delete()) {
                $this->recordDeletion($record);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkDeletion($ids);
        }

        return $count;
    }

    /**
     * الاستعادة المجمعة للمديريات مع التدقيق.
     */
    public function bulkRestore(array $ids): int
    {
        $records = $this->model->newQuery()->onlyTrashed()->whereIn('directorate_id', $ids)->get();
        $count = 0;

        foreach ($records as $record) {
            if ($record->restore()) {
                $this->recordRestore($record);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkRestore($ids);
        }

        return $count;
    }

    /**
     * الحذف النهائي المجمع للمديريات مع التدقيق.
     */
    public function bulkForceDelete(array $ids): int
    {
        $records = $this->model->newQuery()->withTrashed()->whereIn('directorate_id', $ids)->get();
        $count = 0;

        foreach ($records as $record) {
            $data = $record->toArray();
            if ($record->forceDelete()) {
                $this->recordForceDeletion($record, $data);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkForceDelete($ids);
        }

        return $count;
    }
}
