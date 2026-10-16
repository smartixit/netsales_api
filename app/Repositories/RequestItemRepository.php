<?php

namespace App\Repositories;

use App\Interfaces\RequestItemRepositoryInterface;
use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع بنود وعناصر طلبات الكروت (Request Item Repository).
 *
 * @property RequestItem $model
 */
class RequestItemRepository extends BaseRepository implements RequestItemRepositoryInterface
{
    public function __construct(RequestItem $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب بنود طلب محدد.
     */
    public function getItemsByRequestId(int $requestId, array $relations = ['voucherPackage']): Collection
    {
        return $this->model->newQuery()
            ->where('request_id', $requestId)
            ->with($relations)
            ->get();
    }

    /**
     * إضافة بنود متعددة لطلب محدد دفعة واحدة.
     */
    public function createManyForRequest(int $requestId, array $items): bool
    {
        $timestamp = now('Asia/Aden');
        $records = [];

        foreach ($items as $item) {
            $records[] = [
                'request_id' => $requestId,
                'package_id' => (int) $item['package_id'],
                'voucher_count' => (int) $item['voucher_count'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        return $this->model->newQuery()->insert($records);
    }

    /**
     * حذف جميع بنود طلب محدد.
     */
    public function deleteByRequestId(int $requestId): int
    {
        return $this->model->newQuery()->where('request_id', $requestId)->delete();
    }
}
