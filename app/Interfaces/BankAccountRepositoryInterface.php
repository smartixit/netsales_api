<?php

namespace App\Interfaces;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع الحسابات البنكية والمحافظ (Bank Account Repository Interface).
 */
interface BankAccountRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب الحسابات المفعلة لاستقبال المدفوعات.
     */
    public function getActiveAccounts(array $filters = []): Collection;

    /**
     * البحث عن حساب بنكي برقم الحساب والعملة والنوع لمنع التكرار.
     */
    public function findByAccountNumberAndType(string $accountNumber, string $currency, string $type): ?BankAccount;

    /**
     * تبديل حالة تفعيل الحساب البنكي (نشط / معطل).
     */
    public function toggleStatus(int $id): bool;

    /**
     * تحديث رصيد الحساب البنكي بالقفل المتشائم.
     */
    public function updateBalance(int $id, float $amount, bool $isIncrement = true): BankAccount;
}
