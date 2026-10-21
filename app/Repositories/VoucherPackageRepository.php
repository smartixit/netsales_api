<?php

namespace App\Repositories;

use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Models\VoucherPackage;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة باقات وكروت الإنترنت (Enterprise Voucher Package Repository).
 *
 * @property VoucherPackage $model
 */
class VoucherPackageRepository extends BaseRepository implements VoucherPackageRepositoryInterface
{
    public function __construct(VoucherPackage $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة الباقات مع الفلترة والبحث والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $status ? $query->active() : $query->inactive();
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('package_name', 'like', "%{$search}%")
                    ->orWhereHas('network', function ($nq) use ($search) {
                        $nq->where('network_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('package_id', 'desc')->get();
        }

        return $query->orderBy('package_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن باقة عبر معرف الشبكة واسم الباقة.
     */
    public function findByNetworkAndName(int $networkId, string $packageName): ?VoucherPackage
    {
        return $this->model->newQuery()
            ->forNetwork($networkId)
            ->where('package_name', $packageName)
            ->first();
    }

    /**
     * جلب باقات شبكة معينة (افتراضياً الباقات النشطة فقط).
     */
    public function getForNetwork(int $networkId, array $filters = []): Collection|CursorPaginator
    {
        $filters['network_id'] = $networkId;
        if (! isset($filters['status']) && empty($filters['all_statuses'])) {
            $filters['status'] = true;
        }

        return $this->getFiltered($filters, ['network']);
    }
}
