<?php

namespace App\Repositories;

use App\Interfaces\PosRepositoryInterface;
use App\Models\Pos;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة نقاط البيع (Enterprise POS Repository).
 *
 * @property Pos $model
 */
class PosRepository extends BaseRepository implements PosRepositoryInterface
{
    public function __construct(Pos $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة نقاط البيع مع تطبيق الفلاتر والبحث والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = ['governorate', 'directorate']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('pos_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($ownerQ) => $ownerQ->where('user_full_name', 'like', "%{$search}%"))
                    ->orWhere('pos_phone_number', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['governorate_id'])) {
            $query->inGovernorate((int) $filters['governorate_id']);
        }

        if (! empty($filters['directorate_id'])) {
            $query->inDirectorate((int) $filters['directorate_id']);
        }

        if (! empty($filters['network_id'])) {
            $query->linkedToNetwork((int) $filters['network_id']);
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('pos_id', 'desc')->get();
        }

        return $query->orderBy('pos_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن نقطة بيع عبر رقم هاتفها.
     */
    public function findByPhone(string $phone): ?Pos
    {
        return $this->model->newQuery()->where('pos_phone_number', $phone)->first();
    }

    /**
     * البحث عن نقطة بيع عبر معرف UUID.
     */
    public function findByUuid(string $uuid): ?Pos
    {
        return $this->model->newQuery()->where('pos_uuid', $uuid)->first();
    }

    /**
     * التحقق من تفرد اسم نقطة البيع.
     */
    public function isNameUnique(string $name, ?int $ignoreId = null): bool
    {
        $query = $this->model->newQuery()->where('pos_name', $name);
        if ($ignoreId) {
            $query->where('pos_id', '!=', $ignoreId);
        }

        return ! $query->exists();
    }

    /**
     * synchronize phone number for all POS of a given owner
     */
    public function syncPhoneForUser(int $ownerId, string $phone): int
    {
        return $this->model->newQuery()
            ->where('owner_id', $ownerId)
            ->update(['pos_phone_number' => $phone]);
    }
}
