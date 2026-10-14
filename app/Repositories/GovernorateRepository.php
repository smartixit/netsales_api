<?php

namespace App\Repositories;

use App\Interfaces\GovernorateRepositoryInterface;
use App\Models\Governorate;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة المحافظات (Governorate Repository).
 *
 * @property Governorate $model
 */
class GovernorateRepository extends BaseRepository implements GovernorateRepositoryInterface
{
    public function __construct(Governorate $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة المحافظات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['directorates']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($filters['search'])) {
            $query->search(trim($filters['search']));
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('governorate_name')->get();
        }

        return $query->orderBy('governorate_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب المحافظات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['directorates']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->onlyTrashed();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($filters['search'])) {
            $query->search(trim($filters['search']));
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('governorate_id', 'desc')->get();
        }

        return $query->orderBy('governorate_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن محافظة بالاسم.
     */
    public function findByName(string $name): ?Governorate
    {
        return $this->model->newQuery()
            ->where('governorate_name', $name)
            ->first();
    }

    /**
     * جلب كافة المحافظات مع إحصائيات المديريات والشبكات ونقاط البيع.
     */
    public function getWithCounts(): Collection
    {
        return $this->model->newQuery()
            ->withDirectoratesCount()
            ->withNetworksCount()
            ->withPosCount()
            ->orderBy('governorate_name')
            ->get();
    }

    /**
     * الحذف المجمع للمحافظات ناعماً مع التدقيق.
     */
    public function bulkDelete(array $ids): int
    {
        $records = $this->model->newQuery()->whereIn('governorate_id', $ids)->get();
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
     * الاستعادة المجمعة للمحافظات مع التدقيق.
     */
    public function bulkRestore(array $ids): int
    {
        $records = $this->model->newQuery()->onlyTrashed()->whereIn('governorate_id', $ids)->get();
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
     * الحذف النهائي المجمع للمحافظات مع التدقيق.
     */
    public function bulkForceDelete(array $ids): int
    {
        $records = $this->model->newQuery()->withTrashed()->whereIn('governorate_id', $ids)->get();
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
