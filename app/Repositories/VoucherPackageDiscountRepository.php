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

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (! empty($filters['package_id']) && ! empty($filters['pos_id'])) {
            $query->forPackageAndPos((int) $filters['package_id'], (int) $filters['pos_id']);
        } else {
            if (! empty($filters['pos_id'])) {
                $query->where('pos_id', (int) $filters['pos_id']);
            }

            if (! empty($filters['package_id'])) {
                $query->where('package_id', (int) $filters['package_id']);
            }
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $status ? $query->active() : $query->where('status', false);
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
            ->where('network_id', $networkId)
            ->forPackageAndPos($packageId, $posId)
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
}
