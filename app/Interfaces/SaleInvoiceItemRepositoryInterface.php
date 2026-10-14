<?php

namespace App\Interfaces;

use App\Models\SaleInvoiceItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع بنود فواتير مبيعات الكروت (Sale Invoice Item Repository Interface).
 */
interface SaleInvoiceItemRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب بنود فاتورة مبيعات محددة.
     *
     * @return Collection<int, SaleInvoiceItem>
     */
    public function getItemsByInvoiceId(int $invoiceId, array $relations = ['voucherPackage']): Collection;

    /**
     * إضافة بنود متعددة لفاتورة مبيعات دفعة واحدة.
     *
     * @param  array<int, array{package_id: int, voucher_price: float, voucher_count: int, item_discount: float, item_total_amount: float}>  $items
     */
    public function createManyForInvoice(int $invoiceId, array $items): bool;
}
