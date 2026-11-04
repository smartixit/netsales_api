<?php

namespace App\Repositories;

use App\Interfaces\VoucherPackageDiscountRepositoryInterface;
use App\Models\VoucherPackageDiscount;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة خصومات باقات الكروت لنقاط البيع (Enterprise Voucher Package Discount Repository).
 *
 * @property VoucherPackageDiscount $model
 */
class VoucherPackageDiscountRepository extends BaseRepository implements VoucherPackageDiscountRepositoryInterface
{
    public function __construct(VoucherPackageDiscount $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة الخصومات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos', 'voucherPackage']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (isset($filters['network_ids'])) {
            $query->whereIn('network_id', (array) $filters['network_ids']);
        } elseif (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (isset($filters['pos_ids'])) {
            $query->whereIn('pos_id', (array) $filters['pos_ids']);
        } elseif (! empty($filters['package_id']) && ! empty($filters['pos_id'])) {
            $query->forPackageAndPos((int) $filters['package_id'], (int) $filters['pos_id']);
        } else {
            if (! empty($filters['pos_id'])) {
                $query->forPos((int) $filters['pos_id']);
            }

            if (! empty($filters['package_id'])) {
                $query->where('package_id', (int) $filters['package_id']);
            }
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $status ? $query->active() : $query->inactive();
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('network', function ($nq) use ($search) {
                    $nq->where('network_name', 'like', "%{$search}%");
                })->orWhereHas('pos', function ($pq) use ($search) {
                    $pq->where('pos_name', 'like', "%{$search}%");
                })->orWhereHas('voucherPackage', function ($vq) use ($search) {
                    $vq->where('package_name', 'like', "%{$search}%");
                });
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('discount_id', 'desc')->get();
        }

        return $query->orderBy('discount_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن خصم مخصص لنقطة بيع على باقة معينة لشبكة محددة.
     */
    public function findByNetworkPosAndPackage(int $networkId, int $posId, int $packageId): ?VoucherPackageDiscount
    {
        return $this->model->newQuery()
            ->forNetwork($networkId)
            ->forPackageAndPos($packageId, $posId)
            ->first();
    }

    /**
     * البحث عن الخصم الفعلي الساري حالياً لنقطة بيع على باقة معينة لشبكة محددة وفق قيود الصلاحية الزمنية.
     */
    public function findEffectiveDiscount(int $networkId, int $posId, int $packageId): ?VoucherPackageDiscount
    {
        return $this->model->newQuery()
            ->forNetwork($networkId)
            ->forPackageAndPos($packageId, $posId)
            ->effective()
            ->orderBy('discount_id', 'desc')
            ->first();
    }

    /**
     * جلب كافة خصومات نقطة بيع محددة.
     */
    public function getDiscountsForPos(int $posId, array $filters = []): Collection|CursorPaginator
    {
        $filters['pos_id'] = $posId;

        return $this->getFiltered($filters, ['network', 'voucherPackage']);
    }

    /**
     * جلب كافة الخصومات المحددة على باقة كروت معينة.
     */
    public function getDiscountsForPackage(int $packageId, array $filters = []): Collection|CursorPaginator
    {
        $filters['package_id'] = $packageId;

        return $this->getFiltered($filters, ['network', 'pos']);
    }

    /**
     * جلب قائمة الخصومات المحذوفة مؤقتاً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashed(int $perPage = 15, array $filters = []): CursorPaginator
    {
        $perPage = min(max(1, $perPage), 50);
        $query = $this->model->newQuery()->onlyTrashed()->with(['network', 'pos', 'voucherPackage']);

        if (isset($filters['network_ids'])) {
            $query->whereIn('network_id', (array) $filters['network_ids']);
        } elseif (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (isset($filters['pos_ids'])) {
            $query->whereIn('pos_id', (array) $filters['pos_ids']);
        } elseif (! empty($filters['pos_id'])) {
            $query->forPos((int) $filters['pos_id']);
        }

        return $query->orderBy($this->model->getKeyName(), 'desc')->cursorPaginate($perPage);
    }
}
