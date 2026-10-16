<?php

namespace App\Services;

use App\Http\Requests\API\V1\Network\StoreNetworkPosRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkPosRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\PosWalletRepositoryInterface;
use App\Models\NetworkPos;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة ارتباط نقاط البيع بمزودي الشبكات (Network POS Service).
 *
 * تتولى كافة العمليات المنطقية لربط نقاط البيع بالشبكات المزودة:
 * 1. استرجاع الارتباطات مع الفلترة والترقيم بالمؤشر (Cursor Pagination).
 * 2. ربط نقطة بيع بشبكة جديدة مع التحقق من صلاحية الطرفين.
 * 3. تفعيل وإلغاء تفعيل الارتباط بشكل فردي ومجمع (Batch Status).
 * 4. فك الارتباط والحذف الفردي والمجمع عبر DB::transaction.
 */
class NetworkPosService
{
    public function __construct(
        protected NetworkPosRepositoryInterface $networkPosRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected PosWalletRepositoryInterface $posWalletRepository,
    ) {}

    /**
     * جلب قائمة الارتباطات مع الفلترة والبحث والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator
    {
        return $this->networkPosRepository->getFiltered($filters, $relations);
    }

    /**
     * البحث عن ارتباط محدد عبر المعرف.
     */
    public function findById(int $id, array $relations = ['network', 'pos']): ?NetworkPos
    {
        try {
            $networkPos = $this->networkPosRepository->findById($id);

            return ! empty($relations) ? $networkPos->load($relations) : $networkPos;
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * إنشاء ارتباط جديد بين نقطة بيع وشبكة.
     */
    public function create(StoreNetworkPosRequest|array $request): NetworkPos
    {
        if ($request instanceof StoreNetworkPosRequest) {
            $networkId = $request->getNetworkId();
            $posId = $request->getPosId();
            $status = $request->getStatus();
        } else {
            $networkId = (int) $request['network_id'];
            $posId = (int) $request['pos_id'];
            $status = isset($request['status']) ? (bool) $request['status'] : true;
        }

        return DB::transaction(function () use ($networkId, $posId, $status) {
            // التحقق من وجود الشبكة
            try {
                $this->networkRepository->findById($networkId);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'network_id' => ['مزود الشبكة المحدد غير موجود في النظام.'],
                ]);
            }

            // التحقق من وجود نقطة البيع
            try {
                $this->posRepository->findById($posId);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير موجودة في النظام.'],
                ]);
            }

            // منع تكرار الارتباط
            $existing = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
            if ($existing) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع هذه مرتبطة بالفعل بهذه الشبكة مسبقاً.'],
                ]);
            }

            $networkPos = $this->networkPosRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'status' => $status,
            ]);

            // إنشاء محفظة مالية لنقطة البيع لدى هذه الشبكة برصيد 0.00 تلقائياً إذا لم تكن موجودة مسبقاً
            $wallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId);
            if (! $wallet) {
                $this->posWalletRepository->create([
                    'network_id' => $networkId,
                    'pos_id' => $posId,
                    'wallet_balance' => 0.00,
                    'status' => true,
                ]);
            }

            return $networkPos->load(['network', 'pos']);
        });
    }

    /**
     * تحديث حالة الارتباط القائم.
     */
    public function update(int $id, UpdateNetworkPosRequest|array $request): NetworkPos
    {
        try {
            $networkPos = $this->networkPosRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_pos_id' => ['سجل الارتباط المحدد غير موجود.'],
            ]);
        }

        $status = $request instanceof UpdateNetworkPosRequest
            ? $request->getStatus()
            : (bool) $request['status'];

        return DB::transaction(function () use ($networkPos, $status) {
            $this->networkPosRepository->update($networkPos->network_pos_id, [
                'status' => $status,
            ]);

            $networkPos->refresh();

            return $networkPos->load(['network', 'pos']);
        });
    }

    /**
     * تبديل حالة الارتباط (تفعيل / تعطيل).
     */
    public function toggleStatus(int $id): NetworkPos
    {
        try {
            $networkPos = $this->networkPosRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_pos_id' => ['سجل الارتباط المحدد غير موجود.'],
            ]);
        }

        return DB::transaction(function () use ($networkPos) {
            $newStatus = ! (bool) $networkPos->status;
            $this->networkPosRepository->update($networkPos->network_pos_id, [
                'status' => $newStatus,
            ]);

            $networkPos->refresh();

            return $networkPos->load(['network', 'pos']);
        });
    }

    /**
     * حذف أو فك ارتباط نقطة بيع بشبكة.
     */
    public function delete(int $id): bool
    {
        try {
            $networkPos = $this->networkPosRepository->findById($id);
        } catch (ModelNotFoundException) {
            return false;
        }

        return DB::transaction(function () use ($networkPos) {
            return (bool) $this->networkPosRepository->delete($networkPos->network_pos_id);
        });
    }

    /**
     * حذف مجمع لسجلات الارتباط.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->networkPosRepository->bulkDelete($ids);
        });
    }

    /**
     * تغيير مجمع لحالة الارتباطات (تفعيل أو تعطيل مجمع).
     *
     * @param  array<int, int>  $ids
     */
    public function bulkToggleStatus(array $ids, bool $status): int
    {
        return DB::transaction(function () use ($ids, $status) {
            return $this->networkPosRepository->bulkToggleStatus($ids, $status);
        });
    }

    /**
     * جلب نقاط البيع المرتبطة بشبكة محددة.
     */
    public function getPosForNetwork(int $networkId, array $filters = []): Collection|CursorPaginator
    {
        return $this->networkPosRepository->getPosForNetwork($networkId, $filters);
    }

    /**
     * جلب الشبكات المرتبطة بنقطة بيع محددة.
     */
    public function getNetworksForPos(int $posId, array $filters = []): Collection|CursorPaginator
    {
        return $this->networkPosRepository->getNetworksForPos($posId, $filters);
    }
}
