<?php

namespace App\Repositories;

use App\Interfaces\PosWalletRepositoryInterface;
use App\Models\PosWallet;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة محافظ نقاط البيع والوكلاء (POS Wallet Repository).
 *
 * @property PosWallet $model
 */
class PosWalletRepository extends BaseRepository implements PosWalletRepositoryInterface
{
    public function __construct(PosWallet $model)
    {
        parent::__construct($model);
    }

    /**
     * تطبيق الفلاتر الخاصة بمحافظ نقاط البيع.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['network_id']) && ! empty($filters['pos_id'])) {
            $query->forNetworkAndPos((int) $filters['network_id'], (int) $filters['pos_id']);
        } else {
            if (! empty($filters['network_id'])) {
                $query->forNetwork((int) $filters['network_id']);
            }

            if (! empty($filters['pos_id'])) {
                $query->forPos((int) $filters['pos_id']);
            }
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $status ? $query->active() : $query->where('status', false);
        }

        if (isset($filters['min_balance']) && is_numeric($filters['min_balance'])) {
            $query->where('wallet_balance', '>=', (float) $filters['min_balance']);
        }

        if (isset($filters['max_balance']) && is_numeric($filters['max_balance'])) {
            $query->where('wallet_balance', '<=', (float) $filters['max_balance']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('wallet_uuid', 'like', "%{$search}%")
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
     * جلب قائمة محافظ نقاط البيع مع تطبيق الفلاتر والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator
    {
        return parent::getFiltered($filters, $relations);
    }

    /**
     * جلب المحافظ المحذوفة لنقاط البيع مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator
    {
        return parent::getTrashedFiltered($filters, $relations);
    }

    /**
     * البحث عن محفظة نقطة بيع لشبكة محددة مع دعم القفل المتشائم.
     */
    public function findByNetworkAndPos(int $networkId, int $posId, bool $lock = false): ?PosWallet
    {
        $query = $this->model->newQuery()->forNetworkAndPos($networkId, $posId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * البحث عن محفظة نقطة بيع عبر المعرف الفريد UUID مع دعم القفل المتشائم.
     */
    public function findByUuid(string $uuid, bool $lock = false): ?PosWallet
    {
        $query = $this->model->newQuery()->where('wallet_uuid', $uuid);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * زيادة رصيد محفظة نقطة البيع بمقدار محدد.
     */
    public function incrementBalance(int $walletId, float $amount): bool
    {
        $wallet = $this->findById($walletId);
        $oldBalance = (float) $wallet->wallet_balance;
        $wallet->wallet_balance = $oldBalance + $amount;
        $updated = $wallet->save();

        if ($updated) {
            $this->recordModification($wallet, ['wallet_balance' => $oldBalance]);
        }

        return $updated;
    }

    /**
     * خصم من رصيد محفظة نقطة البيع بمقدار محدد.
     */
    public function decrementBalance(int $walletId, float $amount): bool
    {
        $wallet = $this->findById($walletId);
        $oldBalance = (float) $wallet->wallet_balance;
        $wallet->wallet_balance = $oldBalance - $amount;
        $updated = $wallet->save();

        if ($updated) {
            $this->recordModification($wallet, ['wallet_balance' => $oldBalance]);
        }

        return $updated;
    }

    /**
     * تحديث حالة المحفظة (تفعيل/تعطيل).
     */
    public function updateStatus(int $walletId, bool $status): bool
    {
        return (bool) $this->patch($walletId, ['status' => $status]);
    }
}
