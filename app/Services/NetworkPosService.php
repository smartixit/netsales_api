<?php

namespace App\Services;

use App\Http\Requests\API\V1\Network\StoreNetworkPosRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkPosRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\PosWalletRepositoryInterface;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkPosEvent;
use App\Models\Pos;
use App\Services\Billing\BillingEntitlementService;
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
        protected NetworkWalletRepositoryInterface $networkWalletRepository,
        protected BillingEntitlementService $billingEntitlements,
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
            $posUuid = $request->getPosUuid();
            $posId = $request->getPosId();
            $status = $request->getStatus();
        } else {
            $networkId = (int) $request['network_id'];
            $posUuid = $request['pos_uuid'] ?? null;
            $posId = isset($request['pos_id']) ? (int) $request['pos_id'] : null;
            $status = isset($request['status']) ? (bool) $request['status'] : true;
        }

        return DB::transaction(function () use ($networkId, $posUuid, $posId, $status) {
            // 1. التحقق من وجود نقطة البيع
            try {
                if ($posId) {
                    $pos = $this->posRepository->findById($posId);
                } elseif ($posUuid) {
                    $pos = $this->posRepository->findByUuid($posUuid);
                } else {
                    throw new ModelNotFoundException('لم يتم تحديد نقطة البيع.');
                }
                $posId = $pos->pos_id;
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير موجودة في النظام.'],
                ]);
            }

            // 2. قفل سجل الشبكة لتسلسل إنشاء الارتباطات ومنع تجاوز الحصة بالتزامن.
            $network = Network::query()->lockForUpdate()->find($networkId);
            if (! $network) {
                throw ValidationException::withMessages([
                    'network_id' => ['مزود الشبكة المحدد غير موجود في النظام.'],
                ]);
            }

            // 3. التحقق من نشاط نقطة البيع والشبكة عبر دوال حراسة الحالة في الموديلات.
            $pos->ensureActive();
            $network->ensureActive();

            // 4. منع تكرار الارتباط قبل احتساب الحصة؛ الطلب المكرر لا يستهلك مورداً جديداً.
            $existing = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId, true);
            if ($existing && ! $existing->trashed()) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع هذه مرتبطة بالفعل بهذه الشبكة مسبقاً.'],
                ]);
            }

            // 5. فحص الحصة قبل إنشاء ارتباط جديد فقط.
            $this->assertPosQuota($networkId);

            if ($existing && $existing->trashed()) {
                $existing->restore();
                $existing->update(['status' => $status]);
                $this->ensureRelationshipWallet($networkId, $posId);
                $this->recordLifecycleEvent($existing, 'RESTORED', ['status' => $status]);

                return $existing->load(['network', 'pos']);
            }

            $networkPos = $this->networkPosRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'status' => $status,
            ]);

            $this->ensureRelationshipWallet($networkId, $posId);

            $this->recordLifecycleEvent($networkPos, 'CREATED', ['status' => $status]);

            return $networkPos->load(['network', 'pos']);
        });
    }

    /**
     * تحديث حالة الارتباط القائم.
     */
    public function update(int $id, UpdateNetworkPosRequest|array $request): NetworkPos
    {
        $status = $request instanceof UpdateNetworkPosRequest
            ? $request->getStatus()
            : (bool) $request['status'];

        try {
            return DB::transaction(function () use ($id, $status) {
                $networkPos = NetworkPos::query()->lockForUpdate()->findOrFail($id);
                $previousStatus = (bool) $networkPos->status;
                $networkPos->update(['status' => $status]);

                if ($previousStatus !== $status) {
                    $this->recordLifecycleEvent($networkPos, $status ? 'ACTIVATED' : 'DEACTIVATED', ['status' => $status]);
                }

                return $networkPos->load(['network', 'pos']);
            });
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_pos_id' => ['سجل الارتباط المحدد غير موجود.'],
            ]);
        }
    }

    /**
     * تبديل حالة الارتباط (تفعيل / تعطيل).
     */
    public function toggleStatus(int $id): NetworkPos
    {
        try {
            return DB::transaction(function () use ($id) {
                $networkPos = NetworkPos::query()->lockForUpdate()->findOrFail($id);
                $newStatus = ! $networkPos->isActive();
                $networkPos->update(['status' => $newStatus]);
                $this->recordLifecycleEvent($networkPos, $newStatus ? 'ACTIVATED' : 'DEACTIVATED', ['status' => $newStatus]);

                return $networkPos->load(['network', 'pos']);
            });
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_pos_id' => ['سجل الارتباط المحدد غير موجود.'],
            ]);
        }
    }

    /**
     * حذف أو فك ارتباط نقطة بيع بشبكة.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $networkPos = NetworkPos::query()->lockForUpdate()->find($id);
            if (! $networkPos) {
                return false;
            }

            $networkPos->delete();
            $this->recordLifecycleEvent($networkPos, 'ARCHIVED');

            return true;
        });
    }

    public function restore(int $id): ?NetworkPos
    {
        return DB::transaction(function () use ($id): ?NetworkPos {
            // Discover the tenant key without taking a child lock, then use the same
            // parent-first lock order as create() to serialize every quota mutation.
            $candidate = NetworkPos::withTrashed()->find($id);
            if (! $candidate || ! $candidate->trashed()) {
                return null;
            }

            $networkId = (int) $candidate->network_id;
            $network = Network::withTrashed()->lockForUpdate()->find($networkId);
            if (! $network || $network->trashed()) {
                throw ValidationException::withMessages([
                    'network_pos_id' => ['لا يمكن استعادة العلاقة لأن الشبكة محذوفة أو غير موجودة.'],
                ]);
            }

            $link = NetworkPos::withTrashed()->lockForUpdate()->find($id);
            if (! $link || ! $link->trashed()) {
                return null;
            }
            if ((int) $link->network_id !== $networkId) {
                throw ValidationException::withMessages([
                    'network_pos_id' => ['تغير نطاق الارتباط أثناء الاستعادة؛ أعد المحاولة.'],
                ]);
            }

            $pos = Pos::withTrashed()->find($link->pos_id);
            if (! $pos || $pos->trashed()) {
                throw ValidationException::withMessages([
                    'network_pos_id' => ['لا يمكن استعادة العلاقة لأن نقطة البيع محذوفة أو غير موجودة.'],
                ]);
            }

            $network->ensureActive();
            $pos->ensureActive();
            $this->assertPosQuota($networkId);
            $link->restore();
            $this->recordLifecycleEvent($link, 'RESTORED');

            return $link->load(['network', 'pos']);
        }, 3);
    }

    public function findByIdWithTrashed(int $id): ?NetworkPos
    {
        return $this->networkPosRepository->findByIdWithTrashed($id);
    }

    public function getTrashedForNetwork(int $networkId, array $filters = []): Collection|CursorPaginator
    {
        return $this->networkPosRepository->getTrashedForNetwork($networkId, $filters);
    }

    public function getTrashedForPos(int $posId, array $filters = []): Collection|CursorPaginator
    {
        return $this->networkPosRepository->getTrashedForPos($posId, $filters);
    }

    private function ensureRelationshipWallet(int $networkId, int $posId): void
    {
        // Preserve an existing wallet and its complete financial history; initialize only if absent.
        if ($this->posWalletRepository->findByNetworkAndPos($networkId, $posId)) {
            return;
        }

        $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId);
        $this->posWalletRepository->create([
            'network_id' => $networkId,
            'pos_id' => $posId,
            'wallet_balance' => 0.00,
            'opening_balance_source' => 'INITIAL',
            'currency_code' => $networkWallet?->currency_code ?: 'YER',
            'status' => true,
        ]);
    }

    private function recordLifecycleEvent(NetworkPos $link, string $type, array $payload = []): void
    {
        NetworkPosEvent::query()->create([
            'network_pos_id' => $link->network_pos_id,
            'actor_user_id' => auth()->user()?->user_id,
            'event_type' => $type,
            'payload' => $payload === [] ? null : $payload,
        ]);
    }

    /**
     * حذف مجمع لسجلات الارتباط.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $links = NetworkPos::query()->whereIn('network_pos_id', $ids)->lockForUpdate()->get();
            foreach ($links as $link) {
                $link->delete();
                $this->recordLifecycleEvent($link, 'ARCHIVED');
            }

            return $links->count();
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
            $links = NetworkPos::query()
                ->whereIn('network_pos_id', $ids)
                ->lockForUpdate()
                ->get();

            $changed = 0;
            foreach ($links as $link) {
                if ((bool) $link->status === $status) {
                    continue;
                }
                $link->update(['status' => $status]);
                $this->recordLifecycleEvent(
                    $link,
                    $status ? 'ACTIVATED' : 'DEACTIVATED',
                    ['status' => $status, 'source' => 'BULK'],
                );
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * جلب نقاط البيع المرتبطة بشبكة محددة.
     */
    public function getPosForNetwork(int $networkId, array $filters = []): Collection|CursorPaginator
    {
        return $this->networkPosRepository->getPosForNetwork($networkId, $filters);
    }

    private function assertPosQuota(int $networkId): void
    {
        $subscription = $this->billingEntitlements->activeSubscriptionForNetwork($networkId);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => ['يلزم وجود اشتراك نشط أو تجربة سارية قبل ربط نقاط البيع بالشبكة.'],
            ]);
        }

        $currentCount = NetworkPos::query()
            ->where('network_id', $networkId)
            ->count();

        $this->billingEntitlements->assertResourceLimit(
            $subscription,
            'max_pos_count',
            $currentCount
        );
    }

    /**
     * جلب الشبكات المرتبطة بنقطة بيع محددة.
     */
    public function getNetworksForPos(int $posId, array $filters = []): Collection|CursorPaginator
    {
        return $this->networkPosRepository->getNetworksForPos($posId, $filters);
    }
}
