<?php

namespace App\Repositories;

use App\Enums\InvoicePaymentStatus;
use App\Interfaces\SaleInvoiceRepositoryInterface;
use App\Models\SaleInvoice;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
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
     * تطبيق الفلاتر الخاصة بفواتير المبيعات.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (! empty($filters['sale_type'])) {
            $query->where('sale_type', $filters['sale_type']);
        }

        if (! empty($filters['payment_status'])) {
            $statusVal = $filters['payment_status'] instanceof InvoicePaymentStatus
                ? $filters['payment_status']->value
                : (string) $filters['payment_status'];
            $query->where('payment_status', $statusVal);
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
    }

    /**
     * جلب قائمة فواتير المبيعات مع الفلترة المتقدمة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos', 'createdBy', 'items.voucherPackage']): Collection|CursorPaginator
    {
        return parent::getFiltered($filters, $relations);
    }

    /**
     * جلب فواتير المبيعات المحذوفة مؤقتاً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator
    {
        return parent::getTrashedFiltered($filters, $relations);
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
