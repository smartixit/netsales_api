<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\SmsMessageType;
use App\Enums\WalletOperationType;
use App\Helpers\AmountFormatter;
use App\Http\Requests\API\V1\Network\DepositNetworkWalletRequest;
use App\Http\Requests\API\V1\Network\StoreNetworkWalletRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkWalletRequest;
use App\Http\Requests\API\V1\Network\WithdrawNetworkWalletRequest;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkWalletLogRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Models\NetworkWallet;
use App\Models\NetworkWalletLog;
use Exception;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة محافظ مزودي الشبكات ودفتر الأستاذ المالي (Enterprise Network Wallet Service).
 */
class NetworkWalletService
{
    public function __construct(
        protected NetworkWalletRepositoryInterface $networkWalletRepository,
        protected NetworkWalletLogRepositoryInterface $networkWalletLogRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected NotificationService $notificationService,
    ) {}

    /**
     * جلب قائمة محافظ الشبكات مع تطبيق الفلاتر والترقيم.
     */
    public function getWallets(array $filters = []): Collection|CursorPaginator
    {
        return $this->networkWalletRepository->getFiltered($filters, ['network']);
    }

    /**
     * جلب المحافظ المحذوفة مؤقتاً مع الفلترة والترقيم.
     */
    public function getTrashedWallets(array $filters = []): Collection|CursorPaginator
    {
        return $this->networkWalletRepository->getTrashedFiltered($filters, ['network']);
    }

    /**
     * جلب محفظة شبكة عبر معرف المحفظة.
     */
    public function getWalletById(int $walletId): NetworkWallet
    {
        /** @var NetworkWallet $wallet */
        $wallet = $this->networkWalletRepository->findById($walletId);
        $wallet->loadMissing(['network']);

        return $wallet;
    }

    /**
     * جلب محفظة شبكة عبر معرف الشبكة.
     */
    public function getWalletByNetworkId(int $networkId): NetworkWallet
    {
        $wallet = $this->networkWalletRepository->findByNetworkId($networkId);

        if (! $wallet) {
            throw new Exception("لم يتم العثور على محفظة مخصصة لمزود الشبكة برقم ({$networkId}).");
        }

        $wallet->loadMissing(['network']);

        return $wallet;
    }

    /**
     * إنشاء وتخصيص محفظة جديدة لمزود الشبكة.
     */
    public function createWallet(StoreNetworkWalletRequest $request, ?int $userId = null): NetworkWallet
    {
        return DB::transaction(function () use ($request, $userId): NetworkWallet {
            $networkId = $request->getNetworkId();
            $initialBalance = $request->getWalletBalance();
            $status = $request->getStatus();

            // التحقق من وجود الشبكة
            $this->networkRepository->findById($networkId);

            // التحقق من عدم وجود محفظة مسبقة للشبكة
            $existing = $this->networkWalletRepository->findByNetworkId($networkId);
            if ($existing) {
                throw ValidationException::withMessages([
                    'network_id' => ['يوجد بالفعل محفظة مالية مسجلة لهذه الشبكة.'],
                ]);
            }

            /** @var NetworkWallet $wallet */
            $wallet = $this->networkWalletRepository->create([
                'network_id' => $networkId,
                'wallet_balance' => $initialBalance,
                'status' => $status,
            ]);

            $this->networkWalletLogRepository->createLog(
                $wallet->wallet_id,
                WalletOperationType::DEPOSIT->value,
                $initialBalance,
                'رصيد افتتاحي',
                $userId
            );

            $wallet->load('network');

            return $wallet;
        });
    }

    /**
     * تحديث حالة المحفظة (تفعيل / تجميد).
     */
    public function updateStatus(int $walletId, UpdateNetworkWalletRequest $request): NetworkWallet
    {
        return DB::transaction(function () use ($walletId, $request): NetworkWallet {
            $this->networkWalletRepository->findById($walletId);
            $this->networkWalletRepository->updateStatus($walletId, $request->getStatus());

            return $this->getWalletById($walletId);
        });
    }

    /**
     * إيداع رصيد مالي في محفظة الشبكة.
     *
     * @return array{wallet: NetworkWallet, log: NetworkWalletLog}
     */
    public function deposit(int $walletId, DepositNetworkWalletRequest $request, int $userId): array
    {
        return DB::transaction(function () use ($walletId, $request, $userId): array {
            /** @var NetworkWallet $wallet */
            $wallet = $this->networkWalletRepository->findById($walletId);

            if (! $wallet->isActive()) {
                throw ValidationException::withMessages([
                    'status' => ['المحفظة مجمدة أو معطلة حالياً، لا يمكن إتمام عملية الإيداع.'],
                ]);
            }

            $amount = $request->getAmount();
            $statement = $request->getStatement();
            $logDate = $request->getLogDate();

            $this->networkWalletRepository->incrementBalance($walletId, $amount);

            $log = $this->networkWalletLogRepository->createLog(
                $walletId,
                WalletOperationType::DEPOSIT->value,
                $amount,
                $statement,
                $userId,
                $logDate
            );

            $wallet->refresh()->load('network');

            $this->sendWalletSms($wallet, 'receipt', (float) $amount);

            return [
                'wallet' => $wallet,
                'log' => $log,
            ];
        });
    }

    /**
     * سحب رصيد مالي من محفظة الشبكة.
     *
     * @return array{wallet: NetworkWallet, log: NetworkWalletLog}
     */
    public function withdraw(int $walletId, WithdrawNetworkWalletRequest $request, int $userId): array
    {
        return DB::transaction(function () use ($walletId, $request, $userId): array {
            /** @var NetworkWallet $wallet */
            $wallet = $this->networkWalletRepository->findById($walletId);

            if (! $wallet->isActive()) {
                throw ValidationException::withMessages([
                    'status' => ['المحفظة مجمدة أو معطلة حالياً، لا يمكن إتمام عملية السحب.'],
                ]);
            }

            $amount = $request->getAmount();

            if (! $wallet->hasSufficientBalance($amount)) {
                throw ValidationException::withMessages([
                    'amount' => ["رصيد المحفظة غير كافٍ لإتمام السحب. الرصيد الحالي: ({$wallet->wallet_balance})"],
                ]);
            }

            $statement = $request->getStatement();
            $logDate = $request->getLogDate();

            $this->networkWalletRepository->decrementBalance($walletId, $amount);

            $log = $this->networkWalletLogRepository->createLog(
                $walletId,
                WalletOperationType::WITHDRAW->value,
                $amount,
                $statement,
                $userId,
                $logDate
            );

            $wallet->refresh()->load('network');

            $this->sendWalletSms($wallet, 'payment', (float) $amount);

            return [
                'wallet' => $wallet,
                'log' => $log,
            ];
        });
    }

    /**
     * جلب قيود دفتر الأستاذ وسجلات العمليات لمحفظة شبكة محددة.
     */
    public function getLogs(int $walletId, array $filters = []): Collection|CursorPaginator
    {
        // التحقق من وجود المحفظة
        $this->networkWalletRepository->findById($walletId);

        return $this->networkWalletLogRepository->getLogsForWallet($walletId, $filters, ['createdBy']);
    }

    /**
     * حذف محفظة شبكة (Soft Delete).
     */
    public function deleteWallet(int $walletId): bool
    {
        return DB::transaction(function () use ($walletId): bool {
            /** @var NetworkWallet $wallet */
            $wallet = $this->networkWalletRepository->findById($walletId);

            if ((float) $wallet->wallet_balance > 0) {
                throw ValidationException::withMessages([
                    'wallet_balance' => ['لا يمكن حذف محفظة تحتوي على رصيد مالي نشط. يرجى تصفية الرصيد أولاً.'],
                ]);
            }

            return (bool) $this->networkWalletRepository->delete($walletId);
        });
    }

    /**
     * استعادة محفظة محذوفة.
     */
    public function restoreWallet(int $walletId): bool
    {
        return DB::transaction(function () use ($walletId): bool {
            return (bool) $this->networkWalletRepository->restore($walletId);
        });
    }

    /**
     * الحذف النهائي لمحفظة شبكة.
     */
    public function forceDeleteWallet(int $walletId): bool
    {
        return DB::transaction(function () use ($walletId): bool {
            return (bool) $this->networkWalletRepository->forceDelete($walletId);
        });
    }

    /**
     * الحذف المجمع لمحافظ الشبكات.
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            foreach ($ids as $id) {
                $wallet = $this->networkWalletRepository->findById($id);
                if ((float) $wallet->wallet_balance > 0) {
                    throw ValidationException::withMessages([
                        'ids' => ["المحفظة برقم ({$id}) تحتوي على رصيد مالي متبقٍ ولا يمكن حذفها."],
                    ]);
                }
            }

            return $this->networkWalletRepository->bulkDelete($ids);
        });
    }

    /**
     * الاستعادة المجمعة لمحافظ الشبكات.
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->networkWalletRepository->bulkRestore($ids);
        });
    }

    /**
     * الحذف النهائي المجمع لمحافظ الشبكات.
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->networkWalletRepository->bulkForceDelete($ids);
        });
    }

    /**
     * إرسال إشعار SMS لمدير الشبكة عند حركات المحفظة أو تسويات المصروفات.
     *
     * @param  NetworkWallet  $wallet  كائن محفظة الشبكة
     * @param  string  $action  نوع الحركة ('receipt' للقبض والتغذية والاسترداد، 'payment' للصرف والدفع والخصم)
     * @param  float  $amount  المبلغ
     * @param  float|null  $currentBalance  الرصيد بعد الحركة (إن لم يحدد يُؤخذ من المحفظة)
     * @param  string|null  $reason  بيان أو تفصيل إضافي للحركة (مثلاً 'مصروف تشغيلي' أو 'تسوية مصروف')
     */
    public function sendWalletSms(
        NetworkWallet $wallet,
        string $action,
        float $amount,
        ?float $currentBalance = null,
        ?string $reason = null
    ): void {
        $wallet->loadMissing('network');

        $phoneNumber = $wallet->network->network_phone_number;
        if (empty($phoneNumber)) {
            return;
        }

        $networkName = $wallet->network?->network_name ?? "شبكة #{$wallet->network_id}";
        $balance = $currentBalance ?? (float) $wallet->wallet_balance;

        $formattedAmount = AmountFormatter::formatWithCurrency($amount, systemSetting('base_currency', 'YER'));
        $formattedBalance = AmountFormatter::formatWithCurrency($balance, systemSetting('base_currency', 'YER'));

        $actionText = ($action === 'receipt') ? 'لكم سند قبض' : 'عليكم سند صرف';
        $reasonText = $reason ? " ({$reason})" : '';

        $message = "شبكة {$networkName}: {$actionText}{$reasonText} بمبلغ {$formattedAmount} رصيدكم {$formattedBalance}";

        $this->notificationService->sendDirect(
            userId: $wallet->network->owner_id,
            type: NotificationType::WALLET_TRANSFER,
            title: 'حركة مالية',
            message: $message,
            inApp: true,
            push: true,
            sms: true,
            networkId: $wallet->network_id,
            smsMessageType: ($action === 'receipt') ? SmsMessageType::RECEIPT : SmsMessageType::PAYMENT,
            phoneNumberOverride: $phoneNumber
        );
    }
}
