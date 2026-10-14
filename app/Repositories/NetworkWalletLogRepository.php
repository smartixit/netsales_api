<?php

namespace App\Repositories;

use App\Enums\WalletOperationType;
use App\Interfaces\NetworkWalletLogRepositoryInterface;
use App\Models\NetworkWalletLog;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع دفتر أستاذ وسجلات محافظ مزودي الشبكات (Network Wallet Log Repository).
 *
 * @property NetworkWalletLog $model
 */
class NetworkWalletLogRepository extends BaseRepository implements NetworkWalletLogRepositoryInterface
{
    public function __construct(NetworkWalletLog $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة سجلات المحافظ مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['wallet', 'createdBy']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['wallet_id'])) {
            $query->forWallet((int) $filters['wallet_id']);
        }

        if (! empty($filters['log_operation'])) {
            $op = $filters['log_operation'] instanceof WalletOperationType ? $filters['log_operation'] : WalletOperationType::tryFrom($filters['log_operation']);
            if ($op === WalletOperationType::DEPOSIT) {
                $query->deposits();
            } elseif ($op === WalletOperationType::WITHDRAW) {
                $query->withdrawals();
            } else {
                $query->where('log_operation', $filters['log_operation']);
            }
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('log_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('log_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('log_statement', 'like', "%{$search}%")
                    ->orWhereHas('wallet', function ($wq) use ($search) {
                        $wq->where('wallet_uuid', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('log_id', 'desc')->get();
        }

        return $query->orderBy('log_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب سجلات محفظة شبكة محددة مع الفلترة والترقيم بالمؤشر.
     */
    public function getLogsForWallet(int $walletId, array $filters = [], array $relations = ['createdBy']): Collection|CursorPaginator
    {
        $filters['wallet_id'] = $walletId;

        return $this->getFiltered($filters, $relations);
    }

    /**
     * إنشاء قيد مالي جديد في دفتر أستاذ محفظة الشبكة.
     */
    public function createLog(int $walletId, WalletOperationType|string $operation, float $amount, string $statement, ?int $createdBy = null, ?string $date = null): NetworkWalletLog
    {
        return $this->create([
            'wallet_id' => $walletId,
            'log_operation' => $operation instanceof WalletOperationType ? $operation->value : $operation,
            'log_amount' => $amount,
            'log_statement' => $statement,
            'log_date' => $date ?? now('Asia/Aden'),
            'created_by' => $createdBy,
        ]);
    }
}
