<?php

namespace App\Interfaces;

use App\Enums\WalletOperationType;
use App\Models\PosWalletLog;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع سجلات ودفتر أستاذ محافظ نقاط البيع (POS Wallet Log Repository Interface).
 */
interface PosWalletLogRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة سجلات محافظ نقاط البيع مع تطبيق الفلاتر والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, PosWalletLog>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = ['wallet', 'createdBy']): Collection|CursorPaginator;

    /**
     * جلب سجلات محفظة نقطة بيع محددة مع الفلترة والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, PosWalletLog>|CursorPaginator
     */
    public function getLogsForWallet(int $walletId, array $filters = [], array $relations = ['createdBy']): Collection|CursorPaginator;

    /**
     * إنشاء قيد مالي جديد في دفتر أستاذ محفظة نقطة البيع.
     */
    public function createLog(int $walletId, WalletOperationType|string $operationType, float $amount, string $statement, ?int $createdBy = null, ?string $date = null): PosWalletLog;
}
