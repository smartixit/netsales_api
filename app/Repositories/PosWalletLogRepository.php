<?php

namespace App\Repositories;

use App\Support\DecimalAmount;

use App\Enums\WalletOperationType;
use App\Interfaces\PosWalletLogRepositoryInterface;
use App\Models\PosWalletLog;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع دفتر أستاذ وسجلات محافظ نقاط البيع والوكلاء (POS Wallet Log Repository).
 *
 * @property PosWalletLog $model
 */
class PosWalletLogRepository extends BaseRepository implements PosWalletLogRepositoryInterface
{
    public function __construct(PosWalletLog $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة سجلات محافظ نقاط البيع مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['wallet', 'createdBy']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['wallet_id'])) {
            $query->forWallet((int) $filters['wallet_id']);
        }

        if (! empty($filters['log_operation_type'])) {
            $op = $filters['log_operation_type'] instanceof WalletOperationType ? $filters['log_operation_type'] : WalletOperationType::tryFrom($filters['log_operation_type']);
            if ($op === WalletOperationType::DEPOSIT) {
                $query->deposits();
            } elseif ($op === WalletOperationType::WITHDRAW) {
                $query->withdrawals();
            } else {
                $query->where('log_operation_type', $filters['log_operation_type']);
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
     * جلب سجلات محفظة نقطة بيع محددة مع الفلترة والترقيم بالمؤشر.
     */
    public function getLogsForWallet(int $walletId, array $filters = [], array $relations = ['createdBy']): Collection|CursorPaginator
    {
        $filters['wallet_id'] = $walletId;

        return $this->getFiltered($filters, $relations);
    }

    /**
     * إنشاء قيد مالي جديد في دفتر أستاذ محفظة نقطة البيع.
     */
    public function createLog(int $walletId, WalletOperationType|string $operation, float $amount, string $statement, ?int $createdBy = null, ?string $date = null, ?int $transferId = null): PosWalletLog
    {
        $wallet = \App\Models\PosWallet::query()->lockForUpdate()->findOrFail($walletId);
        $operationValue = $operation instanceof WalletOperationType ? $operation->value : $operation;
        $amountMinor = DecimalAmount::toMinorUnits($amount);
        if ($amountMinor < 0) {
            throw new \InvalidArgumentException('Wallet log amounts cannot be negative.');
        }

        $balanceAfterMinor = DecimalAmount::toMinorUnits($wallet->wallet_balance);
        $balanceBeforeMinor = match ($operationValue) {
            WalletOperationType::DEPOSIT->value => DecimalAmount::add($balanceAfterMinor, -$amountMinor),
            WalletOperationType::WITHDRAW->value => DecimalAmount::add($balanceAfterMinor, $amountMinor),
            default => $balanceAfterMinor,
        };
        $currencyCode = strtoupper(trim((string) $wallet->currency_code));
        if (preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1) {
            throw new \LogicException('The POS wallet currency code is invalid.');
        }

        return $this->create([
            'wallet_id' => $walletId,
            'transfer_id' => $transferId,
            'log_operation_type' => $operationValue,
            'log_amount' => DecimalAmount::fromMinorUnits($amountMinor),
            'balance_before' => DecimalAmount::fromMinorUnits($balanceBeforeMinor),
            'balance_after' => DecimalAmount::fromMinorUnits($balanceAfterMinor),
            'balance_snapshot_source' => 'LIVE',
            'currency_code' => $currencyCode,
            'log_statement' => $statement,
            'log_date' => $date ?? now('Asia/Aden'),
            'created_by' => $createdBy,
        ]);
    }
}
