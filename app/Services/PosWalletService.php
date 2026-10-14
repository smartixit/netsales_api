<?php

namespace App\Services;

use App\Enums\WalletOperationType;
use App\Http\Requests\API\V1\Pos\DepositPosWalletRequest;
use App\Http\Requests\API\V1\Pos\StorePosWalletRequest;
use App\Http\Requests\API\V1\Pos\TransferNetworkToPosWalletRequest;
use App\Http\Requests\API\V1\Pos\TransferPosToNetworkWalletRequest;
use App\Http\Requests\API\V1\Pos\UpdatePosWalletRequest;
use App\Http\Requests\API\V1\Pos\WithdrawPosWalletRequest;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkWalletLogRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\PosWalletLogRepositoryInterface;
use App\Interfaces\PosWalletRepositoryInterface;
use App\Models\NetworkWallet;
use App\Models\NetworkWalletLog;
use App\Models\PosWallet;
use App\Models\PosWalletLog;
use Exception;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة محافظ نقاط البيع والتحويلات المالية البينية (Enterprise POS Wallet Service).
 */
class PosWalletService
{
    public function __construct(
        protected PosWalletRepositoryInterface $posWalletRepository,
        protected PosWalletLogRepositoryInterface $posWalletLogRepository,
        protected NetworkWalletRepositoryInterface $networkWalletRepository,
        protected NetworkWalletLogRepositoryInterface $networkWalletLogRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository
    ) {}

    /**
     * جلب قائمة محافظ نقاط البيع مع الفلاتر والترقيم بالمؤشر.
     */
    public function getWallets(array $filters = []): Collection|CursorPaginator
    {
        return $this->posWalletRepository->getFiltered($filters, ['network', 'pos']);
    }

    /**
     * جلب محافظ نقاط البيع المحذوفة مؤقتاً.
     */
    public function getTrashedWallets(array $filters = []): Collection|CursorPaginator
    {
        return $this->posWalletRepository->getTrashedFiltered($filters, ['network', 'pos']);
    }

    /**
     * جلب محفظة نقطة بيع عبر معرف المحفظة.
     */
    public function getWalletById(int $walletId): PosWallet
    {
        /** @var PosWallet $wallet */
        $wallet = $this->posWalletRepository->findById($walletId);
        $wallet->loadMissing(['network', 'pos']);

        return $wallet;
    }

    /**
     * جلب محفظة نقطة بيع عبر معرفي الشبكة ونقطة البيع.
     */
    public function getWalletByNetworkAndPos(int $networkId, int $posId): PosWallet
    {
        $wallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId);

        if (! $wallet) {
            throw new Exception("لم يتم العثور على محفظة لنقطة البيع برقم ({$posId}) تابعة للشبكة برقم ({$networkId}).");
        }

        $wallet->loadMissing(['network', 'pos']);

        return $wallet;
    }

    /**
     * إنشاء محفظة جديدة لنقطة بيع.
     */
    public function createWallet(StorePosWalletRequest $request, ?int $userId = null): PosWallet
    {
        return DB::transaction(function () use ($request, $userId): PosWallet {
            $networkId = $request->getNetworkId();
            $posId = $request->getPosId();
            $initialBalance = $request->getWalletBalance();
            $status = $request->getStatus();

            // التحقق من وجود الشبكة ونقطة البيع
            $this->networkRepository->findById($networkId);
            $this->posRepository->findById($posId);

            // التحقق من عدم وجود محفظة سابقة لنفس الربط
            $existing = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId);
            if ($existing) {
                throw ValidationException::withMessages([
                    'pos_id' => ['توجد بالفعل محفظة مالية لنقطة البيع المحددة لدى هذه الشبكة.'],
                ]);
            }

            /** @var PosWallet $wallet */
            $wallet = $this->posWalletRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'wallet_balance' => $initialBalance,
                'status' => $status,
            ]);

            // قيد افتتاحي إذا كان الرصيد أكبر من صفر
            if ($initialBalance > 0) {
                $this->posWalletLogRepository->createLog(
                    $wallet->wallet_id,
                    WalletOperationType::DEPOSIT->value,
                    $initialBalance,
                    'رصيد افتتاحي عند تدشين محفظة نقطة البيع',
                    $userId
                );
            }

            $wallet->load(['network', 'pos']);

            return $wallet;
        });
    }

    /**
     * تحديث حالة المحفظة (تفعيل / تجميد).
     */
    public function updateStatus(int $walletId, UpdatePosWalletRequest $request): PosWallet
    {
        return DB::transaction(function () use ($walletId, $request): PosWallet {
            $this->posWalletRepository->findById($walletId);
            $this->posWalletRepository->updateStatus($walletId, $request->getStatus());

            return $this->getWalletById($walletId);
        });
    }

    /**
     * إيداع رصيد مالي مباشر في محفظة نقطة البيع.
     *
     * @return array{wallet: PosWallet, log: PosWalletLog}
     */
    public function deposit(int $walletId, DepositPosWalletRequest $request, int $userId): array
    {
        return DB::transaction(function () use ($walletId, $request, $userId): array {
            /** @var PosWallet $wallet */
            $wallet = $this->posWalletRepository->findById($walletId);

            if (! $wallet->isActive()) {
                throw ValidationException::withMessages([
                    'status' => ['المحفظة مجمدة أو معطلة حالياً، لا يمكن إتمام عملية الإيداع.'],
                ]);
            }

            $amount = $request->getAmount();
            $statement = $request->getStatement();
            $logDate = $request->getLogDate();

            $this->posWalletRepository->incrementBalance($walletId, $amount);

            $log = $this->posWalletLogRepository->createLog(
                $walletId,
                WalletOperationType::DEPOSIT->value,
                $amount,
                $statement,
                $userId,
                $logDate
            );

            $wallet->refresh()->load(['network', 'pos']);

            return [
                'wallet' => $wallet,
                'log' => $log,
            ];
        });
    }

    /**
     * سحب رصيد مالي مباشر من محفظة نقطة البيع.
     *
     * @return array{wallet: PosWallet, log: PosWalletLog}
     */
    public function withdraw(int $walletId, WithdrawPosWalletRequest $request, int $userId): array
    {
        return DB::transaction(function () use ($walletId, $request, $userId): array {
            /** @var PosWallet $wallet */
            $wallet = $this->posWalletRepository->findById($walletId);

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

            $this->posWalletRepository->decrementBalance($walletId, $amount);

            $log = $this->posWalletLogRepository->createLog(
                $walletId,
                WalletOperationType::WITHDRAW->value,
                $amount,
                $statement,
                $userId,
                $logDate
            );

            $wallet->refresh()->load(['network', 'pos']);

            return [
                'wallet' => $wallet,
                'log' => $log,
            ];
        });
    }

    /**
     * تحويل رصيد مالي من محفظة الشبكة إلى محفظة نقطة البيع (عملية ذرية مشتركة).
     *
     * @return array{network_wallet: NetworkWallet, pos_wallet: PosWallet, network_log: NetworkWalletLog, pos_log: PosWalletLog}
     */
    public function transferFromNetwork(TransferNetworkToPosWalletRequest $request, int $userId): array
    {
        return DB::transaction(function () use ($request, $userId): array {
            $networkId = $request->getNetworkId();
            $posId = $request->getPosId();
            $amount = $request->getAmount();
            $userStatement = $request->getStatement();

            // قفل محفظة الشبكة والتحقق منها
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_id' => ["لم يتم العثور على محفظة مالية مسجلة للشبكة (#{$networkId})."],
                ]);
            }

            if (! $networkWallet->isActive()) {
                throw ValidationException::withMessages([
                    'network_id' => ['محفظة الشبكة مجمدة أو معطلة حالياً، لا يمكن إجراء التحويل منها.'],
                ]);
            }

            if (! $networkWallet->hasSufficientBalance($amount)) {
                throw ValidationException::withMessages([
                    'amount' => ["رصيد محفظة الشبكة غير كافٍ لإتمام التحويل. الرصيد المتوفر: ({$networkWallet->wallet_balance})"],
                ]);
            }

            // قفل محفظة نقطة البيع والتحقق منها
            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_id' => ["لم يتم العثور على محفظة لنقطة البيع (#{$posId}) لدى هذه الشبكة."],
                ]);
            }

            if (! $posWallet->isActive()) {
                throw ValidationException::withMessages([
                    'pos_id' => ['محفظة نقطة البيع مجمدة أو معطلة حالياً، لا يمكن استقبال رصيد فيها.'],
                ]);
            }

            // الخصم من محفظة الشبكة
            $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $amount);

            $netStatement = "تحويل وتغذية رصيد إلى محفظة نقطة البيع (#{$posId})".($userStatement ? " - {$userStatement}" : '');
            $networkLog = $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::WITHDRAW->value,
                $amount,
                $netStatement,
                $userId
            );

            // الإيداع في محفظة نقطة البيع
            $this->posWalletRepository->incrementBalance($posWallet->wallet_id, $amount);

            $posStatement = "استلام تغذية رصيد من محفظة الشبكة (#{$networkId})".($userStatement ? " - {$userStatement}" : '');
            $posLog = $this->posWalletLogRepository->createLog(
                $posWallet->wallet_id,
                WalletOperationType::DEPOSIT->value,
                $amount,
                $posStatement,
                $userId
            );

            return [
                'network_wallet' => $networkWallet->refresh()->load('network'),
                'pos_wallet' => $posWallet->refresh()->load(['network', 'pos']),
                'network_log' => $networkLog,
                'pos_log' => $posLog,
            ];
        });
    }

    /**
     * استرجاع أو تحويل رصيد من محفظة نقطة البيع إلى محفظة الشبكة (عملية ذرية مشتركة).
     *
     * @return array{pos_wallet: PosWallet, network_wallet: NetworkWallet, pos_log: PosWalletLog, network_log: NetworkWalletLog}
     */
    public function transferToNetwork(TransferPosToNetworkWalletRequest $request, int $userId): array
    {
        return DB::transaction(function () use ($request, $userId): array {
            $networkId = $request->getNetworkId();
            $posId = $request->getPosId();
            $amount = $request->getAmount();
            $userStatement = $request->getStatement();

            // قفل محفظة نقطة البيع والتحقق منها
            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_id' => ["لم يتم العثور على محفظة لنقطة البيع (#{$posId}) لدى هذه الشبكة."],
                ]);
            }

            if (! $posWallet->isActive()) {
                throw ValidationException::withMessages([
                    'pos_id' => ['محفظة نقطة البيع مجمدة أو معطلة حالياً، لا يمكن التحويل منها.'],
                ]);
            }

            if (! $posWallet->hasSufficientBalance($amount)) {
                throw ValidationException::withMessages([
                    'amount' => ["رصيد محفظة نقطة البيع غير كافٍ للتحويل. الرصيد المتاح: ({$posWallet->wallet_balance})"],
                ]);
            }

            // قفل محفظة الشبكة والتحقق منها
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_id' => ["لم يتم العثور على محفظة مالية مسجلة للشبكة (#{$networkId})."],
                ]);
            }

            if (! $networkWallet->isActive()) {
                throw ValidationException::withMessages([
                    'network_id' => ['محفظة الشبكة مجمدة أو معطلة حالياً، لا يمكن استقبال رصيد فيها.'],
                ]);
            }

            // الخصم من محفظة نقطة البيع
            $this->posWalletRepository->decrementBalance($posWallet->wallet_id, $amount);

            $posStatement = "استرجاع رصيد إلى محفظة الشبكة (#{$networkId})".($userStatement ? " - {$userStatement}" : '');
            $posLog = $this->posWalletLogRepository->createLog(
                $posWallet->wallet_id,
                WalletOperationType::WITHDRAW->value,
                $amount,
                $posStatement,
                $userId
            );

            // الإيداع في محفظة الشبكة
            $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $amount);

            $netStatement = "استلام رصيد مسترجع من نقطة البيع (#{$posId})".($userStatement ? " - {$userStatement}" : '');
            $networkLog = $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::DEPOSIT->value,
                $amount,
                $netStatement,
                $userId
            );

            return [
                'pos_wallet' => $posWallet->refresh()->load(['network', 'pos']),
                'network_wallet' => $networkWallet->refresh()->load('network'),
                'pos_log' => $posLog,
                'network_log' => $networkLog,
            ];
        });
    }

    /**
     * جلب قيود دفتر الأستاذ وسجلات العمليات لمحفظة نقطة بيع محددة.
     */
    public function getLogs(int $walletId, array $filters = []): Collection|CursorPaginator
    {
        $this->posWalletRepository->findById($walletId);

        return $this->posWalletLogRepository->getLogsForWallet($walletId, $filters, ['createdBy']);
    }

    /**
     * حذف محفظة نقطة بيع (Soft Delete).
     */
    public function deleteWallet(int $walletId): bool
    {
        return DB::transaction(function () use ($walletId): bool {
            /** @var PosWallet $wallet */
            $wallet = $this->posWalletRepository->findById($walletId);

            if ((float) $wallet->wallet_balance > 0) {
                throw ValidationException::withMessages([
                    'wallet_balance' => ['لا يمكن حذف محفظة نقطة بيع تحتوي على رصيد نشط. يرجى تصفية أو استرجاع الرصيد أولاً.'],
                ]);
            }

            return (bool) $this->posWalletRepository->delete($walletId);
        });
    }

    /**
     * استعادة محفظة نقطة بيع محذوفة.
     */
    public function restoreWallet(int $walletId): bool
    {
        return DB::transaction(function () use ($walletId): bool {
            return (bool) $this->posWalletRepository->restore($walletId);
        });
    }

    /**
     * الحذف النهائي لمحفظة نقطة بيع.
     */
    public function forceDeleteWallet(int $walletId): bool
    {
        return DB::transaction(function () use ($walletId): bool {
            return (bool) $this->posWalletRepository->forceDelete($walletId);
        });
    }

    /**
     * الحذف المجمع لمحافظ نقاط البيع.
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            foreach ($ids as $id) {
                $wallet = $this->posWalletRepository->findById($id);
                if ((float) $wallet->wallet_balance > 0) {
                    throw ValidationException::withMessages([
                        'ids' => ["المحفظة برقم ({$id}) تحتوي على رصيد متبقٍ ولا يمكن حذفها."],
                    ]);
                }
            }

            return $this->posWalletRepository->bulkDelete($ids);
        });
    }

    /**
     * الاستعادة المجمعة لمحافظ نقاط البيع.
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->posWalletRepository->bulkRestore($ids);
        });
    }

    /**
     * الحذف النهائي المجمع لمحافظ نقاط البيع.
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->posWalletRepository->bulkForceDelete($ids);
        });
    }
}
