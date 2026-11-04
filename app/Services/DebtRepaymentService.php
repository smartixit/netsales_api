<?php

namespace App\Services;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NotificationType;
use App\Enums\WalletOperationType;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkWalletLogRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\PosWalletLogRepositoryInterface;
use App\Interfaces\PosWalletRepositoryInterface;
use App\Models\PosRepayment;
use App\Models\PosRepaymentAllocation;
use App\Models\SaleInvoice;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebtRepaymentService
{
    public function __construct(
        protected PosWalletRepositoryInterface $posWalletRepository,
        protected NetworkWalletRepositoryInterface $networkWalletRepository,
        protected PosWalletLogRepositoryInterface $posWalletLogRepository,
        protected NetworkWalletLogRepositoryInterface $networkWalletLogRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected OutboxService $outboxService,
        protected NotificationService $notificationService,
        protected PosWalletService $posWalletService
    ) {}

    /**
     * استرجاع ملخص الذمم المدينة وتحليل أعمار الديون (Multi-Invoice Aging) للشبكة ونقطة البيع.
     *
     * @return array{
     *     total_outstanding_debt: float,
     *     unpaid_invoices_count: int,
     *     partially_paid_invoices_count: int,
     *     aging_buckets: array{
     *         days_0_30: float,
     *         days_31_60: float,
     *         days_61_90: float,
     *         days_over_90: float
     *     },
     *     oldest_outstanding_invoice_date: ?string,
     *     pos_wallet?: array{
     *         credit_limit: ?float,
     *         wallet_balance: float,
     *         current_debt_exposure: float,
     *         available_credit_capacity: float
     *     }
     * }
     */
    public function getReceivablesSummary(int $networkId, ?int $posId = null): array
    {
        $now = Carbon::now('Asia/Aden');
        $d30 = $now->copy()->subDays(30);
        $d60 = $now->copy()->subDays(60);
        $d90 = $now->copy()->subDays(90);

        $baseQuery = SaleInvoice::where('network_id', $networkId)
            ->where('payment_status', '!=', InvoicePaymentStatus::PAID)
            ->where('remaining_amount', '>', 0);

        if ($posId !== null) {
            $baseQuery->where('pos_id', $posId);
        }

        $totalOutstandingDebt = round((float) (clone $baseQuery)->sum('remaining_amount'), 2);
        $unpaidCount = (int) (clone $baseQuery)->where('payment_status', InvoicePaymentStatus::UNPAID)->count();
        $partiallyPaidCount = (int) (clone $baseQuery)->where('payment_status', InvoicePaymentStatus::PARTIALLY_PAID)->count();

        // حساب الديون في كل شريحة عمرية بدقة
        $bucket0To30 = round((float) (clone $baseQuery)->where('invoice_date', '>=', $d30)->sum('remaining_amount'), 2);
        $bucket31To60 = round((float) (clone $baseQuery)->where('invoice_date', '<', $d30)->where('invoice_date', '>=', $d60)->sum('remaining_amount'), 2);
        $bucket61To90 = round((float) (clone $baseQuery)->where('invoice_date', '<', $d60)->where('invoice_date', '>=', $d90)->sum('remaining_amount'), 2);
        $bucketOver90 = round((float) (clone $baseQuery)->where('invoice_date', '<', $d90)->sum('remaining_amount'), 2);

        $oldestDate = (clone $baseQuery)->min('invoice_date');

        $summary = [
            'total_outstanding_debt' => $totalOutstandingDebt,
            'unpaid_invoices_count' => $unpaidCount,
            'partially_paid_invoices_count' => $partiallyPaidCount,
            'aging_buckets' => [
                'days_0_30' => $bucket0To30,
                'days_31_60' => $bucket31To60,
                'days_61_90' => $bucket61To90,
                'days_over_90' => $bucketOver90,
            ],
            'oldest_outstanding_invoice_date' => $oldestDate ? Carbon::parse($oldestDate)->toDateTimeString() : null,
        ];

        if ($posId !== null) {
            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId);
            if ($posWallet) {
                $balance = (float) $posWallet->wallet_balance;
                $summary['pos_wallet'] = [
                    'credit_limit' => $posWallet->credit_limit !== null ? (float) $posWallet->credit_limit : null,
                    'wallet_balance' => $balance,
                    'current_debt_exposure' => $balance < 0 ? abs($balance) : 0.0,
                    'available_credit_capacity' => $posWallet->getAvailableOverdraftCapacity(),
                ];
            }
        }

        return $summary;
    }

    /**
     * استرجاع قائمة الفواتير غير المسددة مع حساب العمر الزمني لكل فاتورة.
     */
    public function getOutstandingInvoices(int $networkId, ?int $posId = null, array $filters = []): Collection|CursorPaginator
    {
        $now = Carbon::now('Asia/Aden');
        $query = SaleInvoice::with(['pos', 'network', 'items.voucherPackage'])
            ->where('network_id', $networkId)
            ->where('payment_status', '!=', InvoicePaymentStatus::PAID)
            ->where('remaining_amount', '>', 0);

        if ($posId !== null) {
            $query->where('pos_id', $posId);
        }

        if (! empty($filters['bucket'])) {
            match ($filters['bucket']) {
                '0_30' => $query->where('invoice_date', '>=', $now->copy()->subDays(30)),
                '31_60' => $query->where('invoice_date', '<', $now->copy()->subDays(30))->where('invoice_date', '>=', $now->copy()->subDays(60)),
                '61_90' => $query->where('invoice_date', '<', $now->copy()->subDays(60))->where('invoice_date', '>=', $now->copy()->subDays(90)),
                'over_90' => $query->where('invoice_date', '<', $now->copy()->subDays(90)),
                default => null,
            };
        }

        $query->orderBy('invoice_date', 'asc')->orderBy('invoice_id', 'asc');

        if (! empty($filters['paginate']) && filter_var($filters['paginate'], FILTER_VALIDATE_BOOLEAN)) {
            $limit = isset($filters['limit']) ? (int) $filters['limit'] : 15;
            $paginator = $query->cursorPaginate($limit);
            foreach ($paginator->items() as $inv) {
                $this->attachAgingAttributes($inv, $now);
            }

            return $paginator;
        }

        $invoices = $query->get();
        foreach ($invoices as $inv) {
            $this->attachAgingAttributes($inv, $now);
        }

        return $invoices;
    }

    /**
     * إلحاق خصائص العمر والشرائح العمرية بالفاتورة.
     */
    protected function attachAgingAttributes(SaleInvoice $invoice, Carbon $now): void
    {
        $invDate = Carbon::parse($invoice->invoice_date);
        $ageDays = max(0, $invDate->diffInDays($now));
        $invoice->setAttribute('age_days', $ageDays);

        if ($ageDays <= 30) {
            $bucket = '0_30';
        } elseif ($ageDays <= 60) {
            $bucket = '31_60';
        } elseif ($ageDays <= 90) {
            $bucket = '61_90';
        } else {
            $bucket = 'over_90';
        }

        $invoice->setAttribute('aging_bucket', $bucket);
    }

    /**
     * استرجاع سجلات سندات السداد وتفاصيل توزيعاتها.
     */
    public function getRepayments(int $networkId, ?int $posId = null, array $filters = []): Collection|CursorPaginator
    {
        $query = PosRepayment::with(['allocations.invoice', 'pos', 'network', 'createdBy', 'reversedBy'])
            ->where('network_id', $networkId);

        if ($posId !== null) {
            $query->where('pos_id', $posId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderBy('repayment_date', 'desc')->orderBy('repayment_id', 'desc');

        if (! empty($filters['paginate']) && filter_var($filters['paginate'], FILTER_VALIDATE_BOOLEAN)) {
            $limit = isset($filters['limit']) ? (int) $filters['limit'] : 15;

            return $query->cursorPaginate($limit);
        }

        return $query->get();
    }

    /**
     * استرجاع تفاصيل سند سداد محدد.
     */
    public function getRepaymentById(int $repaymentId): PosRepayment
    {
        /** @var PosRepayment $repayment */
        $repayment = PosRepayment::with(['allocations.invoice', 'pos', 'network', 'createdBy', 'reversedBy'])
            ->findOrFail($repaymentId);

        return $repayment;
    }

    /**
     * تحصيل سداد مديونية نقطة البيع مع توزيع المبلغ وفق خوارزمية الأقدم فأن الأقدم (FIFO Allocation).
     *
     * @param  array{
     *     network_id: int,
     *     pos_id: int,
     *     amount: float,
     *     payment_method?: string,
     *     reference_number?: ?string,
     *     notes?: ?string
     * }  $data
     */
    public function collectRepayment(array $data, int $userId): PosRepayment
    {
        return DB::transaction(function () use ($data, $userId): PosRepayment {
            $networkId = (int) $data['network_id'];
            $posId = (int) $data['pos_id'];
            $amount = round((float) $data['amount'], 2);
            $paymentMethod = $data['payment_method'] ?? 'CASH';
            $referenceNumber = $data['reference_number'] ?? null;
            $notes = $data['notes'] ?? null;

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['مبلغ السداد يجب أن يكون قيمة موجبة أكبر من الصفر.'],
                ]);
            }

            $network = $this->networkRepository->findById($networkId);
            $network->ensureActive();

            $pos = $this->posRepository->findById($posId);
            $pos->ensureActive();

            // 1. ترتيب الأقفال العالمي الموحد (NetworkWallet -> PosWallet -> Invoices)
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_id' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لإيداع المبالغ المحصلة.'],
                ]);
            }
            $networkWallet->ensureActive();

            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_id' => ['لا توجد محفظة مالية لنقطة البيع لدى هذه الشبكة.'],
                ]);
            }
            $posWallet->ensureActive();

            // 2. قفل الفواتير المستحقة بترتيب حتمي متصاعد (FIFO: invoice_date ASC, invoice_id ASC)
            $invoices = SaleInvoice::where('network_id', $networkId)
                ->where('pos_id', $posId)
                ->where('remaining_amount', '>', 0)
                ->where('payment_status', '!=', InvoicePaymentStatus::PAID)
                ->orderBy('invoice_date', 'asc')
                ->orderBy('invoice_id', 'asc')
                ->lockForUpdate()
                ->get();

            $totalEligibleDebt = round((float) $invoices->sum('remaining_amount'), 2);

            if ($totalEligibleDebt <= 0.001) {
                throw ValidationException::withMessages([
                    'amount' => ['لا توجد أي فواتير بيع آجل مستحقة السداد على نقطة البيع المحددة.'],
                ]);
            }

            if ($amount > ($totalEligibleDebt + 0.001)) {
                $formattedAmount = formatWithCurrency($amount);
                $formattedDebt = formatWithCurrency($totalEligibleDebt);
                throw ValidationException::withMessages([
                    'amount' => ["مبلغ السداد المطلوب ({$formattedAmount}) يتجاوز إجمالي المديونية المستحقة على نقطة البيع ({$formattedDebt}). لا يمكن تحويل الفائض لتغذية المحفظة عبر نافذة السداد."],
                ]);
            }

            // 3. إنشاء سند السداد المالي
            /** @var PosRepayment $repayment */
            $repayment = PosRepayment::create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'repayment_date' => now('Asia/Aden'),
                'created_by' => $userId,
                'status' => 'COMPLETED',
            ]);

            // 4. تنفيذ خوارزمية التوزيع الحتمي (Deterministic FIFO Allocation)
            $remainingToAllocate = $amount;

            foreach ($invoices as $invoice) {
                if ($remainingToAllocate <= 0.001) {
                    break;
                }

                $invRemaining = (float) $invoice->remaining_amount;
                $allocated = min($remainingToAllocate, $invRemaining);

                $newPaid = round((float) $invoice->paid_amount + $allocated, 2);
                $newRemaining = round((float) $invoice->remaining_amount - $allocated, 2);
                $newStatus = ($newRemaining <= 0.001) ? InvoicePaymentStatus::PAID : InvoicePaymentStatus::PARTIALLY_PAID;

                $invoice->paid_amount = $newPaid;
                $invoice->remaining_amount = $newRemaining;
                $invoice->payment_status = $newStatus;
                $invoice->save();

                PosRepaymentAllocation::create([
                    'repayment_id' => $repayment->repayment_id,
                    'invoice_id' => $invoice->invoice_id,
                    'network_id' => $networkId,
                    'pos_id' => $posId,
                    'allocated_amount' => $allocated,
                    'status' => 'ACTIVE',
                    'allocated_at' => now('Asia/Aden'),
                ]);

                $remainingToAllocate = round($remainingToAllocate - $allocated, 2);
            }

            // 5. التسوية المالية في المحافظ وسجلات القيود:
            // أ) محفظة الشبكة: زيادة الرصيد المتاح بالنقد المحصل الفعلي
            $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $amount);
            $networkStatement = "تحصيل سداد مديونية فواتير آجل من نقطة البيع (#{$pos->pos_name}) - سند سداد: (#{$repayment->repayment_uuid})";
            $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::DEPOSIT,
                $amount,
                $networkStatement,
                $userId
            );

            // ب) محفظة الوكيل: تقليص المديونية السالبة وزيادة الرصيد باتجاه الصفر
            $this->posWalletRepository->incrementBalance($posWallet->wallet_id, $amount);
            $newPosBalance = round((float) $posWallet->wallet_balance + $amount, 2);
            $posStatement = "سداد مديونية فواتير كروت آجل (#{$repayment->repayment_uuid}) - رصيد المحفظة الحالي: ({$newPosBalance})";
            $this->posWalletLogRepository->createLog(
                $posWallet->wallet_id,
                WalletOperationType::DEPOSIT,
                $amount,
                $posStatement,
                $userId
            );

            // 6. تسجيل الحدث في صندوق الإرسال اللحظي (Transactional Outbox)
            $this->outboxService->record(
                'POS_REPAYMENT',
                $repayment->repayment_id,
                'DEBT_REPAID',
                [
                    'repayment_id' => $repayment->repayment_id,
                    'repayment_uuid' => $repayment->repayment_uuid,
                    'network_id' => $networkId,
                    'pos_id' => $posId,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'pos_balance_after' => $newPosBalance,
                ]
            );

            // 7. إرسال الإشعارات والـ SMS بعد نجاح الحفظ النهائي (Commit-Safe)
            $posRecipientId = (int) ($pos->owner_id ?? $userId);
            $networkRecipientId = (int) ($network->owner_id ?? $userId);
            $formattedAmount = formatWithCurrency($amount);
            $formattedNewPosBalance = formatWithCurrency($newPosBalance);

            DB::afterCommit(function () use (
                $posRecipientId,
                $networkRecipientId,
                $repayment,
                $pos,
                $formattedAmount,
                $formattedNewPosBalance,
                $posWallet,
                $amount,
                $newPosBalance
            ): void {
                $posWallet->refresh();
                $this->posWalletService->sendWalletSms($posWallet, 'deposit', $amount, $newPosBalance);

                if ($posRecipientId) {
                    $this->notificationService->sendDirect(
                        userId: $posRecipientId,
                        type: NotificationType::WALLET_DEPOSIT,
                        push: true,
                        title: 'سداد مديونية فواتير كروت',
                        message: "تم تسجيل سداد مديونية بمبلغ ({$formattedAmount}) لصالح الشبكة. رصيد المحفظة بعد السداد: ({$formattedNewPosBalance}) - سند رقم (#{$repayment->repayment_uuid})",
                    );
                }

                if ($networkRecipientId) {
                    $this->notificationService->sendDirect(
                        userId: $networkRecipientId,
                        type: NotificationType::WALLET_DEPOSIT,
                        push: true,
                        title: 'تحصيل سداد مديونية كروت',
                        message: "تم تحصيل مبلغ ({$formattedAmount}) كسداد مديونية من نقطة البيع (#{$pos->pos_name}) - سند رقم (#{$repayment->repayment_uuid})",
                    );
                }
            });

            return $repayment->load(['allocations.invoice', 'pos', 'network', 'createdBy']);
        });
    }

    /**
     * عكس سند سداد مديونية مع إعادة قيد الديون واسترداد الأرصدة بدقة (Controlled Repayment Reversal).
     */
    public function reverseRepayment(int $repaymentId, string $reversalReason, int $userId): PosRepayment
    {
        return DB::transaction(function () use ($repaymentId, $reversalReason, $userId): PosRepayment {
            /** @var PosRepayment $repayment */
            $repayment = PosRepayment::where('repayment_id', $repaymentId)->lockForUpdate()->firstOrFail();

            if ($repayment->isReversed()) {
                throw ValidationException::withMessages([
                    'repayment_id' => ['تم عكس سند السداد هذا مسبقاً ولا يمكن عكسه مرة أخرى.'],
                ]);
            }

            $networkId = (int) $repayment->network_id;
            $posId = (int) $repayment->pos_id;
            $amount = (float) $repayment->amount;

            $network = $this->networkRepository->findById($networkId);
            $network->ensureActive();

            $pos = $this->posRepository->findById($posId);
            $pos->ensureActive();

            // 1. ترتيب الأقفال العالمي الموحد (NetworkWallet -> PosWallet)
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_id' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة.'],
                ]);
            }
            $networkWallet->ensureActive();

            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_id' => ['لم يتم العثور على محفظة مالية لنقطة البيع.'],
                ]);
            }
            $posWallet->ensureActive();

            // 2. التحقق من الضمانات المالية للعكس:
            // أ) كفاية رصيد محفظة الشبكة لاسترداد المبلغ المحصل سابقاً
            if (! $networkWallet->hasSufficientBalance($amount)) {
                $formattedBal = formatWithCurrency((float) $networkWallet->wallet_balance);
                $formattedAmount = formatWithCurrency($amount);
                throw ValidationException::withMessages([
                    'amount' => ["رصيد محفظة الشبكة غير كافٍ لعكس هذا السداد ({$formattedAmount}). الرصيد المتاح: ({$formattedBal})."],
                ]);
            }

            // ب) عدم تجاوز السقف الائتماني لنقطة البيع عند إعادة قيد المديونية
            $projectedPosBalance = round((float) $posWallet->wallet_balance - $amount, 2);
            if ($projectedPosBalance < 0) {
                if ($posWallet->credit_limit === null) {
                    throw ValidationException::withMessages([
                        'pos_wallet' => ['لا يمكن عكس السداد لعدم وجود سقف ائتماني معتمد لمحفظة نقطة البيع لاستيعاب الدين المعاد قيده.'],
                    ]);
                }

                $maxLimit = (float) $posWallet->credit_limit;
                if ($projectedPosBalance < (-$maxLimit - 0.001)) {
                    $formattedLimit = formatWithCurrency($maxLimit);
                    throw ValidationException::withMessages([
                        'pos_wallet' => ["لا يمكن عكس السداد لأن الدين المُعاد قيده سيتجاوز السقف الائتماني المعتمد لنقطة البيع ({$formattedLimit})."],
                    ]);
                }
            }

            // 3. قفل الفواتير المعنية بالتوزيعات السابقة بترتيب حتمي
            $allocations = PosRepaymentAllocation::where('repayment_id', $repayment->repayment_id)
                ->where('status', 'ACTIVE')
                ->get();

            $invoiceIds = $allocations->pluck('invoice_id')->unique()->sort()->values()->toArray();
            $invoices = SaleInvoice::whereIn('invoice_id', $invoiceIds)
                ->orderBy('invoice_id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('invoice_id');

            // 4. إعادة الفواتير إلى حالتها ومبالغها الأصلية السابقة
            foreach ($allocations as $allocation) {
                /** @var SaleInvoice $invoice */
                $invoice = $invoices[$allocation->invoice_id];
                $allocatedAmount = (float) $allocation->allocated_amount;

                $restoredPaid = max(0.0, round((float) $invoice->paid_amount - $allocatedAmount, 2));
                $restoredRemaining = min((float) $invoice->invoice_total_amount, round((float) $invoice->remaining_amount + $allocatedAmount, 2));
                $restoredStatus = ($restoredPaid <= 0.001) ? InvoicePaymentStatus::UNPAID : InvoicePaymentStatus::PARTIALLY_PAID;

                $invoice->paid_amount = $restoredPaid;
                $invoice->remaining_amount = $restoredRemaining;
                $invoice->payment_status = $restoredStatus;
                $invoice->save();

                $allocation->status = 'REVERSED';
                $allocation->save();
            }

            // 5. التعديل المالي في المحافظ:
            // أ) خصم النقد المسترد من محفظة الشبكة
            $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $amount);
            $netStatement = "عكس سند سداد مديونية (#{$repayment->repayment_uuid}) لنقطة البيع (#{$pos->pos_name}) - السبب: {$reversalReason}";
            $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::WITHDRAW,
                $amount,
                $netStatement,
                $userId
            );

            // ب) خصم الرصيد من محفظة الوكيل (إعادة قيد المديونية)
            $this->posWalletRepository->decrementBalance($posWallet->wallet_id, $amount);
            $posBalanceAfterReversal = round((float) $posWallet->wallet_balance - $amount, 2);
            $posStatement = "عكس سند سداد مديونية (#{$repayment->repayment_uuid}) - إعادة قيد الدين على المحفظة (الرصيد: {$posBalanceAfterReversal})";
            $this->posWalletLogRepository->createLog(
                $posWallet->wallet_id,
                WalletOperationType::WITHDRAW,
                $amount,
                $posStatement,
                $userId
            );

            // 6. تحديث حالة سند السداد
            $repayment->status = 'REVERSED';
            $repayment->reversed_at = now('Asia/Aden');
            $repayment->reversed_by = $userId;
            $repayment->reversal_reason = $reversalReason;
            $repayment->save();

            // 7. تسجيل رسالة في صندوق الإرسال اللحظي (Transactional Outbox)
            $this->outboxService->record(
                'POS_REPAYMENT',
                $repayment->repayment_id,
                'DEBT_REPAYMENT_REVERSED',
                [
                    'repayment_id' => $repayment->repayment_id,
                    'repayment_uuid' => $repayment->repayment_uuid,
                    'network_id' => $networkId,
                    'pos_id' => $posId,
                    'reversed_amount' => $amount,
                    'reversal_reason' => $reversalReason,
                    'pos_balance_after' => $posBalanceAfterReversal,
                ]
            );

            return $repayment->fresh(['allocations.invoice', 'pos', 'network', 'createdBy', 'reversedBy']);
        });
    }
}
