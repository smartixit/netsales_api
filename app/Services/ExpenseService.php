<?php

namespace App\Services;

use App\Enums\WalletOperationType;
use App\Http\Requests\API\V1\Expense\StoreExpenseItemRequest;
use App\Http\Requests\API\V1\Expense\StoreExpenseRequest;
use App\Http\Requests\API\V1\Expense\UpdateExpenseRequest;
use App\Interfaces\ExpenseAttachmentRepositoryInterface;
use App\Interfaces\ExpenseItemRepositoryInterface;
use App\Interfaces\ExpenseRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkWalletLogRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Services\Support\FileUploadService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة المصروفات التشغيلية والبنود والمرفقات والتسوية المالية (Expense Service).
 */
class ExpenseService
{
    public function __construct(
        protected ExpenseRepositoryInterface $expenseRepository,
        protected ExpenseItemRepositoryInterface $expenseItemRepository,
        protected ExpenseAttachmentRepositoryInterface $expenseAttachmentRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected NetworkWalletRepositoryInterface $networkWalletRepository,
        protected NetworkWalletLogRepositoryInterface $networkWalletLogRepository,
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * جلب قائمة المصروفات مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Expense>|CursorPaginator
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->expenseRepository->getFiltered($filters);
    }

    /**
     * جلب المصروفات المحذوفة ناعماً.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Expense>|CursorPaginator
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->expenseRepository->getTrashedFiltered($filters);
    }

    /**
     * عرض تفاصيل مصروف محدد مع بنوده ومرفقاته.
     */
    public function getById(int $expenseId): Expense
    {
        /** @var Expense $expense */
        $expense = $this->expenseRepository->findById($expenseId);
        $expense->loadMissing(['network', 'createdBy', 'items', 'attachments']);

        return $expense;
    }

    /**
     * تسجيل مصروف تشغيلي جديد مع البنود والمرفقات وخصم إلزامي مباشر من محفظة الشبكة.
     */
    public function create(StoreExpenseRequest $request, int $userId): Expense
    {
        return DB::transaction(function () use ($request, $userId): Expense {
            $networkId = $request->getNetworkId();
            $items = $request->getItems();
            $attachments = $request->getAttachments();
            $expenseDate = $request->getExpenseDate() ?? now('Asia/Aden');

            // 1. التحقق من وجود الشبكة
            $this->networkRepository->findById($networkId);

            // 2. التحقق من وجود ونشاط محفظة الشبكة بقفل متشائم للخصم الإلزامي
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لإتمام صرف المصروفات التشغيلية.'],
                ]);
            }

            if (! $networkWallet->isActive()) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['محفظة مزود الشبكة مجمدة حالياً، لا يمكن سداد مبالغ المصروفات منها.'],
                ]);
            }

            // 3. احتساب إجمالي مبلغ المصروف من البنود التفصيلية
            $totalAmount = 0.0;
            $itemsData = [];

            foreach ($items as $item) {
                $statement = trim((string) $item['item_statement']);
                $amount = round((float) $item['item_amount'], 2);
                $totalAmount += $amount;

                $itemsData[] = [
                    'item_statement' => $statement,
                    'item_amount' => $amount,
                ];
            }

            // 4. إنشاء السجل الرئيسي للمصروف
            /** @var Expense $expense */
            $expense = $this->expenseRepository->create([
                'network_id' => $networkId,
                'expense_total_amount' => $totalAmount,
                'expense_date' => $expenseDate,
                'created_by' => $userId,
            ]);

            // 5. حفظ البنود التفصيلية للمصروف
            $this->expenseItemRepository->createManyForExpense($expense->expense_id, $itemsData);

            // 6. رفع وحفظ المرفقات السحابية (فواتير، سندات صرف)
            foreach ($attachments as $file) {
                if ($file instanceof UploadedFile) {
                    $path = $this->fileUploadService->uploadFile($file, 'expenses');
                    $this->expenseAttachmentRepository->createForExpense($expense->expense_id, [
                        'attachment_path' => $path,
                        'attachment_name' => $file->getClientOriginalName(),
                        'attachment_mime' => $file->getClientMimeType() ?: 'application/octet-stream',
                        'attachment_size' => $file->getSize() ?: 0,
                    ]);
                }
            }

            // 7. الخصم الإلزامي المباشر من محفظة الشبكة وتسجيل القيد في دفتر الأستاذ
            $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $totalAmount);
            $balanceAfter = round((float) $networkWallet->wallet_balance - $totalAmount, 2);

            $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::WITHDRAW,
                $totalAmount,
                "صرف مصروفات تشغيلية (#{$expense->expense_uuid}) - الرصيد بعد الصرف: ({$balanceAfter})",
                $userId,
                $expense->expense_date
            );

            $expense->load(['network', 'createdBy', 'items', 'attachments']);

            return $expense;
        });
    }

    /**
     * تحديث بيانات المصروف وبنوده مع تسوية فرق المبلغ على المحفظة آلياً.
     */
    public function update(int $expenseId, UpdateExpenseRequest $request, int $userId): Expense
    {
        return DB::transaction(function () use ($expenseId, $request, $userId): Expense {
            /** @var Expense $expense */
            $expense = $this->expenseRepository->findById($expenseId);

            $updateData = [];
            if ($request->hasExpenseDate()) {
                $updateData['expense_date'] = $request->getExpenseDate();
            }

            if (! empty($updateData)) {
                $this->expenseRepository->patch($expenseId, $updateData);
            }

            // تحديث البنود إذا تم إرسالها
            $items = $request->getItems();
            if ($items !== null && ! empty($items)) {
                $oldTotal = (float) $expense->expense_total_amount;
                $newTotal = 0.0;
                $itemsData = [];

                foreach ($items as $item) {
                    $statement = trim((string) $item['item_statement']);
                    $amount = round((float) $item['item_amount'], 2);
                    $newTotal += $amount;

                    $itemsData[] = [
                        'item_statement' => $statement,
                        'item_amount' => $amount,
                    ];
                }

                // استبدال البنود
                $this->expenseItemRepository->deleteByExpenseId($expenseId);
                $this->expenseItemRepository->createManyForExpense($expenseId, $itemsData);
                $this->expenseRepository->updateTotalAmount($expenseId, $newTotal);

                // تسوية الفرق المالي على محفظة الشبكة
                $diff = round($newTotal - $oldTotal, 2);
                if ($diff != 0) {
                    $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
                    if ($networkWallet && $networkWallet->isActive()) {
                        if ($diff > 0) {
                            // زيادة في المصروف -> خصم إضافي من المحفظة
                            $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $diff);
                            $this->networkWalletLogRepository->createLog(
                                $networkWallet->wallet_id,
                                WalletOperationType::WITHDRAW,
                                $diff,
                                "تسوية زيادة مصروفات تشغيلية (#{$expense->expense_uuid})",
                                $userId
                            );
                        } else {
                            // انخفاض في المصروف -> استرداد وإيداع الفائض في المحفظة
                            $refund = abs($diff);
                            $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $refund);
                            $this->networkWalletLogRepository->createLog(
                                $networkWallet->wallet_id,
                                WalletOperationType::DEPOSIT,
                                $refund,
                                "استرداد تسوية تخفيض مصروفات تشغيلية (#{$expense->expense_uuid})",
                                $userId
                            );
                        }
                    }
                }
            }

            // إضافة المرفقات الجديدة إن وُجدت
            $attachments = $request->getAttachments();
            foreach ($attachments as $file) {
                if ($file instanceof UploadedFile) {
                    $path = $this->fileUploadService->uploadFile($file, 'expenses');
                    $this->expenseAttachmentRepository->createForExpense($expenseId, [
                        'attachment_path' => $path,
                        'attachment_name' => $file->getClientOriginalName(),
                        'attachment_mime' => $file->getClientMimeType() ?: 'application/octet-stream',
                        'attachment_size' => $file->getSize() ?: 0,
                    ]);
                }
            }

            return $this->getById($expenseId);
        });
    }

    /**
     * حذف ناعم للمصروف.
     */
    public function delete(int $expenseId): bool
    {
        return DB::transaction(function () use ($expenseId): bool {
            $this->expenseRepository->findById($expenseId);

            return (bool) $this->expenseRepository->delete($expenseId);
        });
    }

    /**
     * استعادة المصروف المحذوف ناعماً.
     */
    public function restore(int $expenseId): bool
    {
        return DB::transaction(function () use ($expenseId): bool {
            return (bool) $this->expenseRepository->restore($expenseId);
        });
    }

    /**
     * حذف نهائي للمصروف مع تنظيف المرفقات من التخزين.
     */
    public function forceDelete(int $expenseId): bool
    {
        return DB::transaction(function () use ($expenseId): bool {
            $attachments = $this->expenseAttachmentRepository->getByExpenseId($expenseId);
            foreach ($attachments as $att) {
                $this->fileUploadService->deleteFile($att->attachment_path);
            }

            $this->expenseAttachmentRepository->deleteByExpenseId($expenseId);
            $this->expenseItemRepository->deleteByExpenseId($expenseId);

            return (bool) $this->expenseRepository->forceDelete($expenseId);
        });
    }

    /**
     * حذف مجمع ناعم للمصروفات.
     *
     * @param  array<int, int>  $ids
     */
    public function batchDelete(array $ids): int
    {
        return DB::transaction(fn (): int => $this->expenseRepository->bulkDelete($ids));
    }

    /**
     * استعادة مجمعة للمصروفات.
     *
     * @param  array<int, int>  $ids
     */
    public function batchRestore(array $ids): int
    {
        return DB::transaction(fn (): int => $this->expenseRepository->bulkRestore($ids));
    }

    /**
     * حذف نهائي مجمع للمصروفات.
     *
     * @param  array<int, int>  $ids
     */
    public function batchForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            foreach ($ids as $id) {
                $attachments = $this->expenseAttachmentRepository->getByExpenseId((int) $id);
                foreach ($attachments as $att) {
                    $this->fileUploadService->deleteFile($att->attachment_path);
                }
            }

            return $this->expenseRepository->bulkForceDelete($ids);
        });
    }

    /**
     * إضافة بند منفرد لمصروف قائم مع تسوية المحفظة.
     */
    public function addItem(int $expenseId, StoreExpenseItemRequest $request, int $userId): ExpenseItem
    {
        return DB::transaction(function () use ($expenseId, $request, $userId): ExpenseItem {
            /** @var Expense $expense */
            $expense = $this->expenseRepository->findById($expenseId);

            $amount = $request->getItemAmount();
            $statement = $request->getItemStatement();

            /** @var ExpenseItem $item */
            $item = $this->expenseItemRepository->create([
                'expense_id' => $expenseId,
                'item_statement' => $statement,
                'item_amount' => $amount,
            ]);

            $newTotal = round((float) $expense->expense_total_amount + $amount, 2);
            $this->expenseRepository->updateTotalAmount($expenseId, $newTotal);

            // خصم المبلغ الإضافي من المحفظة
            $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
            if ($networkWallet && $networkWallet->isActive()) {
                $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $amount);
                $this->networkWalletLogRepository->createLog(
                    $networkWallet->wallet_id,
                    WalletOperationType::WITHDRAW,
                    $amount,
                    "إضافة بند مصروف (#{$expense->expense_uuid}): {$statement}",
                    $userId
                );
            }

            return $item;
        });
    }

    /**
     * حذف بند منفرد من مصروف قائم مع استرداد المبلغ للمحفظة.
     */
    public function deleteItem(int $expenseId, int $itemId, int $userId): bool
    {
        return DB::transaction(function () use ($expenseId, $itemId, $userId): bool {
            /** @var Expense $expense */
            $expense = $this->expenseRepository->findById($expenseId);
            /** @var ExpenseItem $item */
            $item = $this->expenseItemRepository->findById($itemId);

            $amount = (float) $item->item_amount;
            $statement = $item->item_statement;

            $this->expenseItemRepository->delete($itemId);

            $newTotal = max(0.0, round((float) $expense->expense_total_amount - $amount, 2));
            $this->expenseRepository->updateTotalAmount($expenseId, $newTotal);

            // استرداد المبلغ إلى المحفظة
            $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
            if ($networkWallet && $networkWallet->isActive()) {
                $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $amount);
                $this->networkWalletLogRepository->createLog(
                    $networkWallet->wallet_id,
                    WalletOperationType::DEPOSIT,
                    $amount,
                    "استرداد حذف بند مصروف (#{$expense->expense_uuid}): {$statement}",
                    $userId
                );
            }

            return true;
        });
    }

    /**
     * حذف مرفق منفرد من المصروف.
     */
    public function deleteAttachment(int $expenseId, int $attachmentId): bool
    {
        return DB::transaction(function () use ($attachmentId): bool {
            $attachment = $this->expenseAttachmentRepository->findById($attachmentId);
            $this->fileUploadService->deleteFile($attachment->attachment_path);

            return (bool) $this->expenseAttachmentRepository->delete($attachmentId);
        });
    }
}
