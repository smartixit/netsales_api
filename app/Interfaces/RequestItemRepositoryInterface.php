<?php

namespace App\Interfaces;

use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع بنود وعناصر طلبات الكروت (Request Item Repository Interface).
 */
interface RequestItemRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب بنود طلب محدد.
     *
     * @return Collection<int, RequestItem>
     */
    public function getItemsByRequestId(int $requestId, array $relations = ['voucherPackage']): Collection;

    /**
     * إضافة بنود متعددة لطلب محدد دفعة واحدة.
     *
     * @param  array<int, array{package_id: int, item_voucher_count: int}>  $items
     */
    public function createManyForRequest(int $requestId, array $items): bool;

    /**
     * حذف جميع بنود طلب محدد.
     */
    public function deleteByRequestId(int $requestId): int;
}
