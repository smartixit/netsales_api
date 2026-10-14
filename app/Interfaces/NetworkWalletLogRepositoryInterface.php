<?php

namespace App\Interfaces;

use App\Enums\WalletOperationType;
use App\Models\NetworkWalletLog;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع سجلات ودفتر أستاذ محافظ الشبكات (Network Wallet Log Repository Interface).
 */
interface NetworkWalletLogRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة سجلات محافظ الشبكات مع تطبيق الفلاتر والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, NetworkWalletLog>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = ['wallet', 'createdBy']): Collection|CursorPaginator;

    /**
     * جلب سجلات محفظة شبكة محددة مع الفلترة والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, NetworkWalletLog>|CursorPaginator
     */
    public function getLogsForWallet(int $walletId, array $filters = [], array $relations = ['createdBy']): Collection|CursorPaginator;

    /**
     * إنشاء قيد مالي جديد في دفتر أستاذ محفظة الشبكة.
     */
    public function createLog(int $walletId, WalletOperationType|string $operation, float $amount, string $statement, ?int $createdBy = null, ?string $date = null): NetworkWalletLog;
}
