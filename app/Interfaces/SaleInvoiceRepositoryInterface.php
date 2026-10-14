<?php

namespace App\Interfaces;

use App\Models\SaleInvoice;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع فواتير مبيعات الكروت (Sale Invoice Repository Interface).
 */
interface SaleInvoiceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة فواتير المبيعات مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, SaleInvoice>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos', 'createdBy', 'items.voucherPackage']): Collection|CursorPaginator;

    /**
     * جلب الفواتير المحذوفة مؤقتاً مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, SaleInvoice>|CursorPaginator
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator;

    /**
     * البحث عن فاتورة مبيعات عبر المعرف الفريد UUID.
     */
    public function findByUuid(string $uuid, bool $lock = false): ?SaleInvoice;
}
