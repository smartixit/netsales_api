<?php

namespace App\Repositories;

use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Models\NetworkWallet;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة محافظ مزودي الشبكات (Network Wallet Repository).
 *
 * @property NetworkWallet $model
 */
class NetworkWalletRepository extends BaseRepository implements NetworkWalletRepositoryInterface
{
    public function __construct(NetworkWallet $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة محافظ الشبكات مع تطبيق الفلاتر والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
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
                        $nq->where('network_name', 'like', "%{$search}%")
                            ->orWhere('network_phone_number', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('wallet_id', 'desc')->get();
        }

        return $query->orderBy('wallet_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب المحافظ المحذوفة مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->onlyTrashed()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('wallet_uuid', 'like', "%{$search}%")
                    ->orWhereHas('network', function ($nq) use ($search) {
                        $nq->where('network_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('wallet_id', 'desc')->get();
        }

        return $query->orderBy('wallet_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن محفظة شبكة عبر معرف الشبكة مع دعم القفل المتشائم.
     */
    public function findByNetworkId(int $networkId, bool $lock = false): ?NetworkWallet
    {
        $query = $this->model->newQuery()->where('network_id', $networkId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * البحث عن محفظة شبكة عبر المعرف الفريد UUID مع دعم القفل المتشائم.
     */
    public function findByUuid(string $uuid, bool $lock = false): ?NetworkWallet
    {
        $query = $this->model->newQuery()->where('wallet_uuid', $uuid);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * زيادة رصيد المحفظة بمقدار محدد.
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
     * خصم من رصيد المحفظة بمقدار محدد.
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
