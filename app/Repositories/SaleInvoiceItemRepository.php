<?php

namespace App\Repositories;

use App\Interfaces\SaleInvoiceItemRepositoryInterface;
use App\Models\SaleInvoiceItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع بنود فواتير مبيعات الكروت (Sale Invoice Item Repository).
 *
 * @property SaleInvoiceItem $model
 */
class SaleInvoiceItemRepository extends BaseRepository implements SaleInvoiceItemRepositoryInterface
{
    public function __construct(SaleInvoiceItem $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب بنود فاتورة مبيعات محددة.
     */
    public function getItemsByInvoiceId(int $invoiceId, array $relations = ['voucherPackage']): Collection
    {
        return $this->model->newQuery()
            ->where('invoice_id', $invoiceId)
            ->with($relations)
            ->get();
    }

    /**
     * إضافة بنود متعددة لفاتورة مبيعات دفعة واحدة.
     */
    public function createManyForInvoice(int $invoiceId, array $items): bool
    {
        $timestamp = now('Asia/Aden');
        $records = [];

        foreach ($items as $item) {
            $records[] = [
                'invoice_id' => $invoiceId,
                'package_id' => (int) $item['package_id'],
                'voucher_price' => (float) $item['voucher_price'],
                'voucher_count' => (int) $item['voucher_count'],
                'item_discount' => (float) ($item['item_discount'] ?? 0.0),
                'item_total_amount' => (float) $item['item_total_amount'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        return $this->model->newQuery()->insert($records);
    }
}
