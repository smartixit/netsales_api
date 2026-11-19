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
use App\Support\DecimalAmount;
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
        protected FileUploadService $fileUploadService,
        protected NetworkWalletService $networkWalletService
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

            // 1. التحقق من وجود الشبكة ونشاطها
            $network = $this->networkRepository->findById($networkId);
            $network->ensureActive();

            // 2. التحقق من وجود ونشاط محفظة الشبكة بقفل متشائم للخصم الإلزامي
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لإتمام صرف المصروفات التشغيلية.'],
                ]);
            }
            $networkWallet->ensureActive();

            // 3. احتساب إجمالي مبلغ المصروف من البنود التفصيلية
            $totalMinor = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $statement = trim((string) $item['item_statement']);
                $itemAmount = (float) $item['item_amount'];
                $itemAmountMinor = DecimalAmount::toMinorUnits($itemAmount);
                $totalMinor = DecimalAmount::add($totalMinor, $itemAmountMinor);

                $itemsData[] = [
                    'item_statement' => $statement,
                    'item_amount' => (float) DecimalAmount::fromMinorUnits($itemAmountMinor),
                ];
            }

            $totalAmount = (float) DecimalAmount::fromMinorUnits($totalMinor);

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
            $networkWallet->refresh();
            $balanceAfter = (float) $networkWallet->wallet_balance;

            $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::WITHDRAW,
                $totalAmount,
                "صرف مصروفات تشغيلية (#{$expense->expense_uuid}) - الرصيد بعد الصرف: ({$balanceAfter})",
                $userId,
                $expense->expense_date
            );

            $this->networkWalletService->sendWalletSms(
                $networkWallet,
                'payment',
                (float) $totalAmount,
                $balanceAfter,
                'مصروف تشغيلي'
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

            $network = $this->networkRepository->findById($expense->network_id);
            $network->ensureActive();

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
                $oldTotalMinor = DecimalAmount::toMinorUnits((float) $expense->expense_total_amount);
                $newTotalMinor = 0;
                $itemsData = [];

                foreach ($items as $item) {
                    $statement = trim((string) $item['item_statement']);
                    $itemAmount = (float) $item['item_amount'];
                    $itemAmountMinor = DecimalAmount::toMinorUnits($itemAmount);
                    $newTotalMinor = DecimalAmount::add($newTotalMinor, $itemAmountMinor);

                    $itemsData[] = [
                        'item_statement' => $statement,
                        'item_amount' => (float) DecimalAmount::fromMinorUnits($itemAmountMinor),
                    ];
                }

                $newTotal = (float) DecimalAmount::fromMinorUnits($newTotalMinor);

                // استبدال البنود
                $this->expenseItemRepository->deleteByExpenseId($expenseId);
                $this->expenseItemRepository->createManyForExpense($expenseId, $itemsData);
                $this->expenseRepository->updateTotalAmount($expenseId, $newTotal);

                // تسوية الفرق المالي على محفظة الشبكة
                $diffMinor = DecimalAmount::add($newTotalMinor, -$oldTotalMinor);
                if ($diffMinor !== 0) {
                    $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
                    if (! $networkWallet) {
                        throw ValidationException::withMessages([
                            'network_wallet' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لتسوية فروق المصروفات.'],
                        ]);
                    }
                    $networkWallet->ensureActive();

                    if ($diffMinor > 0) {
                        // زيادة في المصروف -> خصم إضافي من المحفظة
                        $diff = (float) DecimalAmount::fromMinorUnits($diffMinor);
                        $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $diff);
                        $networkWallet->refresh();
                        $balanceAfter = (float) $networkWallet->wallet_balance;
                        $this->networkWalletLogRepository->createLog(
                            $networkWallet->wallet_id,
                            WalletOperationType::WITHDRAW,
                            $diff,
                            "تسوية زيادة مصروفات تشغيلية (#{$expense->expense_uuid})",
                            $userId
                        );

                        $this->networkWalletService->sendWalletSms(
                            $networkWallet,
                            'payment',
                            (float) $diff,
                            $balanceAfter,
                            'تسوية زيادة مصروف'
                        );
                    } else {
                        // انخفاض في المصروف -> استرداد وإيداع الفائض في المحفظة
                        $refund = (float) DecimalAmount::fromMinorUnits(abs($diffMinor));
                        $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $refund);
                        $networkWallet->refresh();
                        $balanceAfter = (float) $networkWallet->wallet_balance;
                        $this->networkWalletLogRepository->createLog(
                            $networkWallet->wallet_id,
                            WalletOperationType::DEPOSIT,
                            $refund,
                            "استرداد تسوية تخفيض مصروفات تشغيلية (#{$expense->expense_uuid})",
                            $userId
                        );

                        $this->networkWalletService->sendWalletSms(
                            $networkWallet,
                            'receipt',
                            (float) $refund,
                            $balanceAfter,
                            'تسوية استرداد مصروف'
                        );
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
     * حذف ناعم للمصروف مع عكس مالي واسترجاع المبلغ لمحفظة الشبكة.
     */
    public function delete(int $expenseId, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($expenseId, $userId): bool {
            /** @var Expense $expense */
            $expense = $this->expenseRepository->findById($expenseId);

            // تسوية مالية: استرجاع كامل مبلغ المصروف لمحفظة الشبكة عند الإلغاء أو الحذف
            $amount = (float) $expense->expense_total_amount;
            if ($amount > 0) {
                $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
                if ($networkWallet) {
                    $networkWallet->ensureActive();
                    $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $amount);
                    $networkWallet->refresh();
                    $balanceAfter = (float) $networkWallet->wallet_balance;

                    $this->networkWalletLogRepository->createLog(
                        $networkWallet->wallet_id,
                        WalletOperationType::DEPOSIT,
                        $amount,
                        "استرداد إلغاء/حذف مصروف تشغيلي (#{$expense->expense_uuid}) - الرصيد بعد الاسترداد: ({$balanceAfter})",
                        $userId
                    );

                    $this->networkWalletService->sendWalletSms(
                        $networkWallet,
                        'receipt',
                        $amount,
                        $balanceAfter,
                        'استرداد حذف مصروف'
                    );
                }
            }

            return (bool) $this->expenseRepository->delete($expenseId);
        });
    }

    /**
     * استعادة المصروف المحذوف ناعماً مع إعادة الخصم من المحفظة.
     */
    public function restore(int $expenseId, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($expenseId, $userId): bool {
            /** @var Expense $expense */
            $expense = Expense::onlyTrashed()->findOrFail($expenseId);

            // تسوية مالية: إعادة خصم مبلغ المصروف من المحفظة عند الاستعادة مع التحقق من كفاية الرصيد
            $amount = (float) $expense->expense_total_amount;
            if ($amount > 0) {
                $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
                if (! $networkWallet) {
                    throw ValidationException::withMessages([
                        'network_wallet' => ['لم يتم العثور على محفظة مسجلة للشبكة لإعادة قيد المصروف المستعاد.'],
                    ]);
                }
                $networkWallet->ensureActive();

                if ($networkWallet->wallet_balance < $amount) {
                    throw ValidationException::withMessages([
                        'wallet_balance' => ['لا يمكن استعادة المصروف: رصيد محفظة الشبكة غير كافٍ لإعادة الخصم.'],
                    ]);
                }

                $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $amount);
                $networkWallet->refresh();
                $balanceAfter = (float) $networkWallet->wallet_balance;

                $this->networkWalletLogRepository->createLog(
                    $networkWallet->wallet_id,
                    WalletOperationType::WITHDRAW,
                    $amount,
                    "إعادة قيد مصروف تشغيلي مستعاد (#{$expense->expense_uuid}) - الرصيد بعد الخصم: ({$balanceAfter})",
                    $userId
                );

                $this->networkWalletService->sendWalletSms(
                    $networkWallet,
                    'payment',
                    $amount,
                    $balanceAfter,
                    'إعادة قيد مصروف مستعاد'
                );
            }

            return (bool) $this->expenseRepository->restore($expenseId);
        });
    }

    /**
     * حذف نهائي للمصروف مع تنظيف المرفقات من التخزين وعكس مالي إذا لم يُحذف ناعماً مسبقاً.
     */
    public function forceDelete(int $expenseId, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($expenseId, $userId): bool {
            /** @var Expense $expense */
            $expense = Expense::withTrashed()->findOrFail($expenseId);

            // إذا كان المصروف غير محذوف ناعماً (أي لم يتم عكسه مالياً بعد)، نعكسه مالياً قبل الحذف النهائي
            if ($expense->deleted_at === null) {
                $amount = (float) $expense->expense_total_amount;
                if ($amount > 0) {
                    $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
                    if ($networkWallet) {
                        $networkWallet->ensureActive();
                        $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $amount);
                        $networkWallet->refresh();
                        $balanceAfter = (float) $networkWallet->wallet_balance;

                        $this->networkWalletLogRepository->createLog(
                            $networkWallet->wallet_id,
                            WalletOperationType::DEPOSIT,
                            $amount,
                            "استرداد حذف نهائي لمصروف تشغيلي (#{$expense->expense_uuid})",
                            $userId
                        );

                        $this->networkWalletService->sendWalletSms(
                            $networkWallet,
                            'receipt',
                            $amount,
                            $balanceAfter,
                            'استرداد حذف نهائي لمصروف'
                        );
                    }
                }
            }

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
     * حذف مجمع ناعم للمصروفات مع العكس المالي.
     *
     * @param  array<int, int>  $ids
     */
    public function batchDelete(array $ids, ?int $userId = null): int
    {
        return DB::transaction(function () use ($ids, $userId): int {
            $count = 0;
            foreach ($ids as $id) {
                if ($this->delete((int) $id, $userId)) {
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * استعادة مجمعة للمصروفات مع إعادة الخصم المالي.
     *
     * @param  array<int, int>  $ids
     */
    public function batchRestore(array $ids, ?int $userId = null): int
    {
        return DB::transaction(function () use ($ids, $userId): int {
            $count = 0;
            foreach ($ids as $id) {
                if ($this->restore((int) $id, $userId)) {
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * حذف نهائي مجمع للمصروفات مع تنظيف المرفقات والعكس المالي.
     *
     * @param  array<int, int>  $ids
     */
    public function batchForceDelete(array $ids, ?int $userId = null): int
    {
        return DB::transaction(function () use ($ids, $userId): int {
            $count = 0;
            foreach ($ids as $id) {
                if ($this->forceDelete((int) $id, $userId)) {
                    $count++;
                }
            }

            return $count;
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

            $network = $this->networkRepository->findById($expense->network_id);
            $network->ensureActive();

            $amount = (float) $request->getItemAmount();
            $statement = $request->getItemStatement();

            /** @var ExpenseItem $item */
            $item = $this->expenseItemRepository->create([
                'expense_id' => $expenseId,
                'item_statement' => $statement,
                'item_amount' => $amount,
            ]);

            $oldTotalMinor = DecimalAmount::toMinorUnits((float) $expense->expense_total_amount);
            $itemAmountMinor = DecimalAmount::toMinorUnits($amount);
            $newTotal = (float) DecimalAmount::fromMinorUnits(DecimalAmount::add($oldTotalMinor, $itemAmountMinor));
            $this->expenseRepository->updateTotalAmount($expenseId, $newTotal);

            // خصم المبلغ الإضافي من المحفظة
            $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لإتمام صرف المصروفات.'],
                ]);
            }
            $networkWallet->ensureActive();

            $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $amount);
            $networkWallet->refresh();
            $balanceAfter = (float) $networkWallet->wallet_balance;
            $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::WITHDRAW,
                $amount,
                "إضافة بند مصروف (#{$expense->expense_uuid}): {$statement}",
                $userId
            );

            $this->networkWalletService->sendWalletSms(
                $networkWallet,
                'payment',
                (float) $amount,
                $balanceAfter,
                'إضافة بند مصروف'
            );

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

            $network = $this->networkRepository->findById($expense->network_id);
            $network->ensureActive();

            /** @var ExpenseItem $item */
            $item = $this->expenseItemRepository->findById($itemId);

            $amount = (float) $item->item_amount;
            $statement = $item->item_statement;

            $this->expenseItemRepository->delete($itemId);

            $oldTotalMinor = DecimalAmount::toMinorUnits((float) $expense->expense_total_amount);
            $itemAmountMinor = DecimalAmount::toMinorUnits($amount);
            $newTotalMinor = max(0, DecimalAmount::add($oldTotalMinor, -$itemAmountMinor));
            $newTotal = (float) DecimalAmount::fromMinorUnits($newTotalMinor);
            $this->expenseRepository->updateTotalAmount($expenseId, $newTotal);

            // استرداد المبلغ إلى المحفظة
            $networkWallet = $this->networkWalletRepository->findByNetworkId($expense->network_id, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لاسترداد مبلغ البند المحذوف.'],
                ]);
            }
            $networkWallet->ensureActive();

            $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $amount);
            $networkWallet->refresh();
            $balanceAfter = (float) $networkWallet->wallet_balance;
            $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::DEPOSIT,
                $amount,
                "استرداد حذف بند مصروف (#{$expense->expense_uuid}): {$statement}",
                $userId
            );

            $this->networkWalletService->sendWalletSms(
                $networkWallet,
                'receipt',
                (float) $amount,
                $balanceAfter,
                'استرداد حذف بند مصروف'
            );

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

    /**
     * إحصائيات وملخص المصروفات التشغيلية.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters = []): array
    {
        $query = Expense::query();

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        } elseif (! empty($filters['network_ids'])) {
            $query->whereIn('network_id', (array) $filters['network_ids']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('expense_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('expense_date', '<=', $filters['to_date']);
        }

        $totalCount = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('expense_total_amount');

        $now = now('Asia/Aden');
        $thisMonthAmount = (float) (clone $query)
            ->whereYear('expense_date', $now->year)
            ->whereMonth('expense_date', $now->month)
            ->sum('expense_total_amount');

        $todayAmount = (float) (clone $query)
            ->whereDate('expense_date', $now->toDateString())
            ->sum('expense_total_amount');

        return [
            'total_count' => $totalCount,
            'total_amount' => $totalAmount,
            'this_month_amount' => $thisMonthAmount,
            'today_amount' => $todayAmount,
        ];
    }
}
