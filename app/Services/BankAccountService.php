<?php

namespace App\Services;

use App\Http\Requests\API\V1\BankAccount\StoreBankAccountRequest;
use App\Http\Requests\API\V1\BankAccount\UpdateBankAccountRequest;
use App\Interfaces\BankAccountRepositoryInterface;
use App\Models\BankAccount;
use App\Services\Support\FileUploadService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة الحسابات البنكية والمحافظ الإلكترونية للنظام (Bank Account Service).
 */
class BankAccountService
{
    public function __construct(
        protected BankAccountRepositoryInterface $bankAccountRepository,
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * جلب قائمة الحسابات البنكية مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->bankAccountRepository->getFiltered($filters);
    }

    /**
     * جلب الحسابات المفعلة لاستقبال المدفوعات.
     */
    public function getActiveAccounts(array $filters = []): Collection
    {
        return $this->bankAccountRepository->getActiveAccounts($filters);
    }

    /**
     * جلب الحسابات البنكية المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->bankAccountRepository->getTrashedFiltered($filters);
    }

    /**
     * جلب تفاصيل حساب بنكي محدد عبر معرفه.
     */
    public function getById(int $id): BankAccount
    {
        /** @var BankAccount|null $account */
        $account = $this->bankAccountRepository->findById($id);
        if (! $account) {
            throw ValidationException::withMessages([
                'account_id' => ["الحساب البنكي برقم [{$id}] غير موجود."],
            ]);
        }

        return $account;
    }

    /**
     * إنشاء حساب بنكي جديد مع رفع الشعار إن وجد.
     */
    public function create(StoreBankAccountRequest $request): BankAccount
    {
        return DB::transaction(function () use ($request) {
            $accountNumber = $request->getAccountNumber();
            $currency = $request->getAccountCurrency();
            $type = $request->getAccountType();

            $existing = $this->bankAccountRepository->findByAccountNumberAndType($accountNumber, $currency, $type);
            if ($existing) {
                throw ValidationException::withMessages([
                    'account_number' => ['هذا الحساب مسجل مسبقاً بنفس العملة والنوع.'],
                ]);
            }

            $logoPath = null;
            if ($request->hasFile('bank_logo')) {
                $logoPath = $this->fileUploadService->uploadFile(
                    $request->file('bank_logo'),
                    'banks/logos'
                );
            }

            $accountData = [
                'bank_name' => $request->getBankName(),
                'bank_logo' => $logoPath,
                'account_holder_name' => $request->getAccountHolderName(),
                'account_number' => $accountNumber,
                'account_type' => $type,
                'account_currency' => $currency,
                'account_balance' => $request->getAccountBalance() ?? 0.00,
                'status' => $request->getStatus() ?? true,
            ];

            /** @var BankAccount $account */
            $account = $this->bankAccountRepository->create($accountData);

            return $account;
        });
    }

    /**
     * تحديث بيانات الحساب البنكي واستبدال الشعار إن تم تمريره.
     */
    public function update(int $id, UpdateBankAccountRequest $request): BankAccount
    {
        return DB::transaction(function () use ($id, $request) {
            $account = $this->getById($id);

            $accountNumber = $request->has('account_number') ? $request->getAccountNumber() : $account->account_number;
            $currency = $request->has('account_currency') ? $request->getAccountCurrency() : $account->account_currency;
            $type = $request->has('account_type') ? $request->getAccountType() : $account->account_type->value;

            $existing = $this->bankAccountRepository->findByAccountNumberAndType($accountNumber, $currency, $type);
            if ($existing && $existing->account_id !== $id) {
                throw ValidationException::withMessages([
                    'account_number' => ['رقم الحساب مسجل مسبقاً لحساب آخر بنفس العملة والنوع.'],
                ]);
            }

            $updateData = [];
            if ($request->has('bank_name')) {
                $updateData['bank_name'] = $request->getBankName();
            }
            if ($request->has('account_holder_name')) {
                $updateData['account_holder_name'] = $request->getAccountHolderName();
            }
            if ($request->has('account_number')) {
                $updateData['account_number'] = $accountNumber;
            }
            if ($request->has('account_type')) {
                $updateData['account_type'] = $type;
            }
            if ($request->has('account_currency')) {
                $updateData['account_currency'] = $currency;
            }
            if ($request->has('account_balance')) {
                $updateData['account_balance'] = $request->getAccountBalance();
            }
            if ($request->has('status')) {
                $updateData['status'] = $request->getStatus();
            }

            if ($request->hasFile('bank_logo')) {
                if ($account->bank_logo) {
                    $this->fileUploadService->deleteFile($account->bank_logo);
                }
                $updateData['bank_logo'] = $this->fileUploadService->uploadFile(
                    $request->file('bank_logo'),
                    'banks/logos'
                );
            }

            if (! empty($updateData)) {
                $this->bankAccountRepository->update($id, $updateData);
            }

            return $this->getById($id);
        });
    }

    /**
     * تبديل حالة تفعيل الحساب البنكي (نشط / معطل).
     */
    public function toggleStatus(int $id): bool
    {
        $this->getById($id);

        return $this->bankAccountRepository->toggleStatus($id);
    }

    /**
     * حذف حساب بنكي ناعماً.
     */
    public function delete(int $id): bool
    {
        $this->getById($id);

        return (bool) $this->bankAccountRepository->delete($id);
    }

    /**
     * استعادة حساب بنكي محذوف ناعماً.
     */
    public function restore(int $id): bool
    {
        $account = $this->bankAccountRepository->findTrashedById($id);
        if (! $account) {
            throw ValidationException::withMessages([
                'account_id' => ["الحساب البنكي المحذوف برقم [{$id}] غير موجود."],
            ]);
        }

        return (bool) $this->bankAccountRepository->restore($id);
    }

    /**
     * حذف نهائي للحساب البنكي مع تنظيف شعاره من السحابة.
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $account = $this->bankAccountRepository->findTrashedById($id);
            if (! $account) {
                throw ValidationException::withMessages([
                    'account_id' => ["الحساب البنكي المحذوف برقم [{$id}] غير موجود."],
                ]);
            }

            if ($account->bank_logo) {
                $this->fileUploadService->deleteFile($account->bank_logo);
            }

            return (bool) $this->bankAccountRepository->forceDelete($id);
        });
    }

    /**
     * حذف مجمع ناعم للحسابات البنكية.
     */
    public function batchDelete(array $ids): int
    {
        return $this->bankAccountRepository->bulkDelete($ids);
    }

    /**
     * استعادة مجمعة للحسابات البنكية.
     */
    public function batchRestore(array $ids): int
    {
        return $this->bankAccountRepository->bulkRestore($ids);
    }

    /**
     * حذف نهائي مجمع للحسابات البنكية مع تنظيف الصور.
     */
    public function batchForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $account = $this->bankAccountRepository->findTrashedById((int) $id);
                if ($account && $account->bank_logo) {
                    $this->fileUploadService->deleteFile($account->bank_logo);
                }
            }

            return $this->bankAccountRepository->bulkForceDelete($ids);
        });
    }
}
