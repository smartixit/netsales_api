<?php

namespace App\Repositories;

use App\Interfaces\NetworkRepositoryInterface;
use App\Models\Network;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة مزودي الشبكات (Enterprise Network Repository).
 *
 * @property Network $model
 */
class NetworkRepository extends BaseRepository implements NetworkRepositoryInterface
{
    public function __construct(Network $model)
    {
        parent::__construct($model);
    }

    /**
     * تطبيق الفلاتر الخاصة بمزودي الشبكات.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('network_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($ownerQ) => $ownerQ->where('user_full_name', 'like', "%{$search}%"))
                    ->orWhere('network_phone_number', 'like', "%{$search}%");
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
    }

    /**
     * جلب قائمة مزودي الشبكات مع تطبيق الفلاتر والبحث والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = ['governorate', 'directorate']): Collection|CursorPaginator
    {
        return parent::getFiltered($filters, $relations);
    }

    /**
     * جلب قائمة مزودي الشبكات المحذوفة مؤقتاً مع تطبيق الفلاتر والبحث والترقيم.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['governorate', 'directorate']): Collection|CursorPaginator
    {
        return parent::getTrashedFiltered($filters, $relations);
    }

    /**
     * البحث عن شبكة عبر رقم هاتفها.
     */
    public function findByPhone(string $phone): ?Network
    {
        return $this->model->newQuery()->where('network_phone_number', $phone)->first();
    }

    /**
     * البحث عن شبكة عبر معرف UUID.
     */
    public function findByUuid(string $uuid): ?Network
    {
        return $this->model->newQuery()->where('network_uuid', $uuid)->first();
    }

    /**
     * التحقق من تفرد اسم الشبكة.
     */
    public function isNameUnique(string $name, ?int $ignoreId = null): bool
    {
        $query = $this->model->newQuery()->where('network_name', $name);
        if ($ignoreId) {
            $query->where('network_id', '!=', $ignoreId);
        }

        return ! $query->exists();
    }
    /**
     * synchronize phone number for all networks of a given owner
     */
    public function syncPhoneForUser(int $ownerId, string $phone): int
    {
        return $this->model->newQuery()
            ->where('owner_id', $ownerId)
            ->update(['network_phone_number' => $phone]);
    }

    /**
     * جلب الشبكة مع مالكها ونقاط البيع التابعة لها.
     */
    public function findWithStakeholders(int $networkId): ?Network
    {
        return $this->model->newQuery()
            ->with(['owner', 'pos.owner'])
            ->find($networkId);
    }
}
