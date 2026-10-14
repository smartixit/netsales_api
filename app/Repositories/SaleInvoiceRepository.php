<?php

namespace App\Repositories;

use App\Interfaces\SaleInvoiceRepositoryInterface;
use App\Models\SaleInvoice;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع فواتير مبيعات الكروت (Enterprise Sale Invoice Repository).
 *
 * @property SaleInvoice $model
 */
class SaleInvoiceRepository extends BaseRepository implements SaleInvoiceRepositoryInterface
{
    public function __construct(SaleInvoice $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة فواتير المبيعات مع الفلترة المتقدمة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos', 'createdBy', 'items.voucherPackage']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (! empty($filters['pos_id'])) {
            $query->where('pos_id', (int) $filters['pos_id']);
        }

        if (! empty($filters['invoice_sale_type'])) {
            $query->where('invoice_sale_type', $filters['invoice_sale_type']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_uuid', 'like', "%{$search}%")
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
            return $query->orderBy('invoice_id', 'desc')->get();
        }

        return $query->orderBy('invoice_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب فواتير المبيعات المحذوفة مؤقتاً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->onlyTrashed()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (! empty($filters['pos_id'])) {
            $query->where('pos_id', (int) $filters['pos_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_uuid', 'like', "%{$search}%")
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
            return $query->orderBy('invoice_id', 'desc')->get();
        }

        return $query->orderBy('invoice_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن فاتورة مبيعات عبر المعرف الفريد UUID.
     */
    public function findByUuid(string $uuid, bool $lock = false): ?SaleInvoice
    {
        $query = $this->model->newQuery()->where('invoice_uuid', $uuid);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
