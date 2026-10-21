<?php

namespace App\Repositories;

use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Models\NetworkWallet;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
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
     * تطبيق الفلاتر الخاصة بمحافظ الشبكات.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $status ? $query->active() : $query->inactive();
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
    }

    /**
     * جلب قائمة محافظ الشبكات مع تطبيق الفلاتر والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network']): Collection|CursorPaginator
    {
        return parent::getFiltered($filters, $relations);
    }

    /**
     * جلب المحافظ المحذوفة مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network']): Collection|CursorPaginator
    {
        return parent::getTrashedFiltered($filters, $relations);
    }

    /**
     * البحث عن محفظة شبكة عبر معرف الشبكة مع دعم القفل المتشائم.
     */
    public function findByNetworkId(int $networkId, bool $lock = false): ?NetworkWallet
    {
        $query = $this->model->newQuery()->forNetwork($networkId);

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
