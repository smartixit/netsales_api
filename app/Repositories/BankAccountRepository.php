<?php

namespace App\Repositories;

use App\Interfaces\BankAccountRepositoryInterface;
use App\Models\BankAccount;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * مستودع إدارة الحسابات البنكية والمحافظ (Enterprise Bank Account Repository).
 *
 * @property BankAccount $model
 */
class BankAccountRepository extends BaseRepository implements BankAccountRepositoryInterface
{
    public function __construct(BankAccount $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة الحسابات البنكية مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $status ? $query->active() : $query->inactive();
        }

        if (! empty($filters['account_type'])) {
            $query->byType($filters['account_type']);
        }

        if (! empty($filters['account_currency'])) {
            $query->byCurrency($filters['account_currency']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('bank_name', 'like', "%{$search}%")
                    ->orWhere('account_holder_name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%");
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('account_id', 'desc')->get();
        }

        return $query->orderBy('account_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب الحسابات المفعّلة لاستقبال المدفوعات.
     */
    public function getActiveAccounts(array $filters = []): Collection
    {
        $query = $this->model->newQuery()->active();

        if (! empty($filters['account_currency'])) {
            $query->byCurrency($filters['account_currency']);
        }

        if (! empty($filters['account_type'])) {
            $query->byType($filters['account_type']);
        }

        return $query->orderBy('bank_name', 'asc')->get();
    }

    /**
     * البحث عن حساب بنكي برقم الحساب والعملة والنوع.
     */
    public function findByAccountNumberAndType(string $accountNumber, string $currency, string $type): ?BankAccount
    {
        return $this->model->newQuery()
            ->where('account_number', $accountNumber)
            ->byCurrency($currency)
            ->byType($type)
            ->first();
    }

    /**
     * تبديل حالة تفعيل الحساب بالقفل المتشائم.
     */
    public function toggleStatus(int $id): bool
    {
        /** @var BankAccount|null $account */
        $account = $this->model->newQuery()->where('account_id', $id)->lockForUpdate()->first();
        if (! $account) {
            return false;
        }

        $oldStatus = $account->status;
        $newStatus = ! $oldStatus;
        $account->status = $newStatus;
        $account->save();

        $this->recordModification($account, ['status' => $oldStatus]);

        return $newStatus;
    }

    /**
     * تحديث رصيد الحساب البنكي بالقفل المتشائم.
     */
    public function updateBalance(int $id, float $amount, bool $isIncrement = true): BankAccount
    {
        /** @var BankAccount|null $account */
        $account = $this->model->newQuery()->where('account_id', $id)->lockForUpdate()->first();
        if (! $account) {
            throw new RuntimeException("الحساب البنكي برقم [{$id}] غير موجود.");
        }

        $currentBalance = (float) $account->account_balance;
        $newBalance = $isIncrement ? ($currentBalance + $amount) : ($currentBalance - $amount);

        $account->account_balance = $newBalance;
        $account->save();

        $this->recordModification($account, ['account_balance' => $currentBalance]);

        return $account;
    }
}
