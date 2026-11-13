<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\SmsMessageType;
use App\Enums\WalletOperationType;
use App\Helpers\AmountFormatter;
use App\Http\Requests\API\V1\Pos\DepositPosWalletRequest;
use App\Http\Requests\API\V1\Pos\StorePosWalletRequest;
use App\Http\Requests\API\V1\Pos\TransferNetworkToPosWalletRequest;
use App\Http\Requests\API\V1\Pos\TransferPosToNetworkWalletRequest;
use App\Http\Requests\API\V1\Pos\UpdatePosWalletRequest;
use App\Http\Requests\API\V1\Pos\WithdrawPosWalletRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
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
use App\Models\User;
use App\Models\WalletTransfer;
use Exception;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        protected PosRepositoryInterface $posRepository,
        protected NotificationService $notificationService,
        protected ?NetworkPosRepositoryInterface $networkPosRepository = null,
    ) {
        $this->networkPosRepository = $networkPosRepository ?? app(NetworkPosRepositoryInterface::class);
    }

    /**
     * جلب قائمة محافظ نقاط البيع مع الفلاتر والترقيم بالمؤشر.
     */
    public function getWallets(array $filters = []): Collection|CursorPaginator
    {
        return $this->posWalletRepository->getFiltered($filters, ['network', 'pos']);
    }

    /**
     * Retrieve only POS wallets visible within the authenticated user's tenants.
     */
    public function getWalletsForUser(array $filters, User $user): Collection|CursorPaginator
    {
        if (! $user->isSuperAdmin()) {
            $filters['authorized_user_id'] = (int) $user->user_id;
        }

        return $this->getWallets($filters);
    }

    /**
     * جلب محافظ نقاط البيع المحذوفة مؤقتاً.
     */
    public function getTrashedWallets(array $filters = []): Collection|CursorPaginator
    {
        return $this->posWalletRepository->getTrashedFiltered($filters, ['network', 'pos']);
    }

    /**
     * Retrieve deleted POS wallets within the authenticated user's tenant scope.
     */
    public function getTrashedWalletsForUser(array $filters, User $user): Collection|CursorPaginator
    {
        if (! $user->isSuperAdmin()) {
            $filters['authorized_user_id'] = (int) $user->user_id;
        }

        return $this->getTrashedWallets($filters);
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
        $network = $this->networkRepository->findById($networkId);
        $pos = $this->posRepository->findById($posId);

        if (! $wallet) {
            throw new Exception("لم يتم العثور على محفظة لنقطة البيع ({$pos->pos_name}) تابعة لشبكة ({$network->network_name}).");
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

            // التحقق من وجود ونشاط الشبكة ونقطة البيع
            $network = $this->networkRepository->findById($networkId);
            $network->ensureActive();

            $pos = $this->posRepository->findById($posId);
            $pos->ensureActive();

            // التحقق من عدم وجود محفظة سابقة لنفس الربط
            $existing = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId);
            if ($existing) {
                throw ValidationException::withMessages([
                    'pos_id' => ['توجد بالفعل محفظة مالية لنقطة البيع المحددة لدى هذه الشبكة.'],
                ]);
            }

            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if ($networkWallet === null) {
                throw ValidationException::withMessages([
                    'network_id' => ['يجب إنشاء محفظة الشبكة قبل إنشاء محفظة نقطة البيع.'],
                ]);
            }

            $currencyCode = strtoupper(trim((string) $networkWallet->currency_code));
            if (preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1) {
                throw ValidationException::withMessages([
                    'currency_code' => ['رمز عملة محفظة الشبكة غير صالح.'],
                ]);
            }

            /** @var PosWallet $wallet */
            $wallet = $this->posWalletRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'wallet_balance' => $initialBalance,
                'opening_balance_source' => 'INITIAL',
                'currency_code' => $currencyCode,
                'status' => $status,
            ]);

            $this->posWalletLogRepository->createLog(
                $wallet->wallet_id,
                WalletOperationType::DEPOSIT->value,
                $initialBalance,
                'رصيد افتتاحي عند تدشين محفظة نقطة البيع',
                $userId
            );

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
     * تحديث أو إلغاء السقف الائتماني المعتمد لنقطة البيع مع الحماية الصارمة من التخفيض غير الآمن.
     */
    public function updateCreditLimit(int $walletId, ?float $newCreditLimit, int $userId): PosWallet
    {
        return DB::transaction(function () use ($walletId, $newCreditLimit, $userId): PosWallet {
            /** @var PosWallet $wallet */
            $wallet = $this->posWalletRepository->findByIdForUpdate($walletId);
            $wallet->ensureActive();

            if ($wallet->wallet_balance < 0) {
                $currentDebt = abs((float) $wallet->wallet_balance);
                $formattedDebt = formatWithCurrency($currentDebt);

                if ($newCreditLimit === null) {
                    throw ValidationException::withMessages([
                        'credit_limit' => ["لا يمكن إلغاء السقف الائتماني لوجود مديونية قائمة على المحفظة ({$formattedDebt})."],
                    ]);
                }

                if ($newCreditLimit < ($currentDebt - 0.001)) {
                    $formattedNewLimit = formatWithCurrency($newCreditLimit);
                    throw ValidationException::withMessages([
                        'credit_limit' => ["لا يمكن تخفيض السقف الائتماني إلى ({$formattedNewLimit}) وهو أقل من إجمالي المديونية القائمة حالياً ({$formattedDebt})."],
                    ]);
                }
            }

            $wallet->credit_limit = $newCreditLimit;
            $wallet->save();

            $limitText = $newCreditLimit !== null ? formatWithCurrency($newCreditLimit) : 'بدون سقف ائتماني';
            $this->posWalletLogRepository->createLog(
                $wallet->wallet_id,
                WalletOperationType::DEPOSIT,
                0.00,
                "تحديث السقف الائتماني للمحفظة إلى: ({$limitText})",
                $userId
            );

            return $wallet->fresh(['network', 'pos']);
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
            $wallet->ensureActive();

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

            $this->sendWalletSms($wallet, 'receipt', (float) $amount);

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
            $wallet->ensureActive();

            $amount = $request->getAmount();

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

            $this->sendWalletSms($wallet, 'payment', (float) $amount);

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
            $pos = $this->posRepository->findById($posId);
            $amount = $request->getAmount();
            $userStatement = $request->getStatement();

            $minTransfer = (float) systemSetting('min_wallet_transfer_amount', 100);
            $maxTransfer = (float) systemSetting('max_wallet_transfer_amount', 1000000);
            $currencySymbol = systemSetting('currency_symbol', 'ر.ي');
            if ($amount < $minTransfer) {
                throw ValidationException::withMessages([
                    'amount' => ["مبلغ التحويل يقل عن الحد الأدنى المسموح به للنظام ({$minTransfer} {$currencySymbol})."],
                ]);
            }
            if ($amount > $maxTransfer) {
                throw ValidationException::withMessages([
                    'amount' => ["مبلغ التحويل يتجاوز الحد الأقصى المسموح به للنظام ({$maxTransfer} {$currencySymbol})."],
                ]);
            }

            // التحقق من ارتباط نقطة البيع بالشبكة وتفعيله
            $link = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
            if (! $link) {
                throw ValidationException::withMessages([
                    'pos_id' => ["نقطة البيع (#{$pos->pos_name}) غير مرتبطة بهذه الشبكة."],
                ]);
            }
            $link->ensureActive();

            // قفل محفظة الشبكة والتحقق منها
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            $network = $this->networkRepository->findById($networkId);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_id' => ["لم يتم العثور على محفظة مالية مسجلة للشبكة (#{$network->network_name})."],
                ]);
            }
            $networkWallet->ensureActive();

            if (! $networkWallet->hasSufficientBalance($amount)) {
                throw ValidationException::withMessages([
                    'amount' => ["رصيد محفظة الشبكة غير كافٍ لإتمام التحويل. الرصيد المتوفر: ({$networkWallet->wallet_balance})"],
                ]);
            }

            // قفل محفظة نقطة البيع والتحقق منها
            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_id' => ["لم يتم العثور على محفظة لنقطة البيع (#{$pos->pos_name}) لدى هذه الشبكة."],
                ]);
            }
            $posWallet->ensureActive();
            $this->assertMatchingWalletCurrencies($networkWallet, $posWallet);

            // إنشاء سجل الحوالة المالية المركزية
            $transfer = WalletTransfer::create([
                'transfer_uuid' => (string) Str::uuid(),
                'network_wallet_id' => $networkWallet->wallet_id,
                'pos_wallet_id' => $posWallet->wallet_id,
                'amount' => $amount,
                'direction' => 'NET_TO_POS',
                'statement' => $userStatement,
                'created_by' => $userId,
            ]);

            // الخصم من محفظة الشبكة
            $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $amount);

            $netStatement = "تحويل وتغذية رصيد إلى محفظة نقطة البيع (#{$pos->pos_name})".($userStatement ? " - {$userStatement}" : '');
            $networkLog = $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::WITHDRAW->value,
                $amount,
                $netStatement,
                $userId,
                null,
                $transfer->transfer_id
            );

            // الإيداع في محفظة نقطة البيع
            $this->posWalletRepository->incrementBalance($posWallet->wallet_id, $amount);

            $posStatement = "استلام تغذية رصيد من محفظة الشبكة (#{$network->network_name})".($userStatement ? " - {$userStatement}" : '');
            $posLog = $this->posWalletLogRepository->createLog(
                $posWallet->wallet_id,
                WalletOperationType::DEPOSIT->value,
                $amount,
                $posStatement,
                $userId,
                null,
                $transfer->transfer_id
            );

            $posWallet->refresh()->load(['network', 'pos']);

            $this->sendWalletSms($posWallet, 'receipt', (float) $amount);

            return [
                'transfer' => $transfer,
                'network_wallet' => $networkWallet->refresh()->load('network'),
                'pos_wallet' => $posWallet,
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
            $network = $this->networkRepository->findById($networkId);
            $posId = $request->getPosId();
            $pos = $this->posRepository->findById($posId);
            $amount = $request->getAmount();
            $userStatement = $request->getStatement();

            // التحقق من ارتباط نقطة البيع بالشبكة وتفعيله
            $link = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
            if (! $link) {
                throw ValidationException::withMessages([
                    'pos_id' => ["نقطة البيع (#{$pos->pos_name}) غير مرتبطة بهذه الشبكة."],
                ]);
            }
            $link->ensureActive();

            // قفل محفظة الشبكة أولاً وفق ترتيب الأقفال العالمي الموحد لمنع التعارض الحبيبي (Deadlock Prevention)
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_id' => ["لم يتم العثور على محفظة مالية مسجلة للشبكة (#{$network->network_name})."],
                ]);
            }
            $networkWallet->ensureActive();

            // قفل محفظة نقطة البيع ثانياً والتحقق من كفاية الرصيد
            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_id' => ["لم يتم العثور على محفظة لنقطة البيع (#{$pos->pos_name}) لدى هذه الشبكة."],
                ]);
            }
            $posWallet->ensureActive();
            $this->assertMatchingWalletCurrencies($networkWallet, $posWallet);

            if (! $posWallet->hasSufficientBalance($amount)) {
                throw ValidationException::withMessages([
                    'amount' => ["رصيد محفظة نقطة البيع غير كافٍ للتحويل. الرصيد المتاح: ({$posWallet->wallet_balance})"],
                ]);
            }

            // إنشاء سجل الحوالة المالية المركزية
            $transfer = WalletTransfer::create([
                'transfer_uuid' => (string) Str::uuid(),
                'network_wallet_id' => $networkWallet->wallet_id,
                'pos_wallet_id' => $posWallet->wallet_id,
                'amount' => $amount,
                'direction' => 'POS_TO_NET',
                'statement' => $userStatement,
                'created_by' => $userId,
            ]);

            // الخصم من محفظة نقطة البيع
            $this->posWalletRepository->decrementBalance($posWallet->wallet_id, $amount);

            $posStatement = "استرجاع رصيد إلى محفظة الشبكة (#{$network->network_name})".($userStatement ? " - {$userStatement}" : '');
            $posLog = $this->posWalletLogRepository->createLog(
                $posWallet->wallet_id,
                WalletOperationType::WITHDRAW->value,
                $amount,
                $posStatement,
                $userId,
                null,
                $transfer->transfer_id
            );

            // الإيداع في محفظة الشبكة
            $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $amount);

            $netStatement = "استلام رصيد مسترجع من نقطة البيع (#{$pos->pos_name})".($userStatement ? " - {$userStatement}" : '');
            $networkLog = $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::DEPOSIT->value,
                $amount,
                $netStatement,
                $userId,
                null,
                $transfer->transfer_id
            );

            $posWallet->refresh()->load(['network', 'pos']);

            $this->sendWalletSms($posWallet, 'payment', (float) $amount);

            return [
                'transfer' => $transfer,
                'pos_wallet' => $posWallet,
                'network_wallet' => $networkWallet->refresh()->load('network'),
                'pos_log' => $posLog,
                'network_log' => $networkLog,
            ];
        });
    }

    /**
     * عكس عملية تحويل مالي بين محفظة الشبكة ومحفظة نقطة البيع ذرياً مع منع عكس العمليات العكسية.
     *
     * @return array{original_transfer: WalletTransfer, reversal_transfer: WalletTransfer, network_wallet: NetworkWallet, pos_wallet: PosWallet, network_log: NetworkWalletLog, pos_log: PosWalletLog}
     */
    public function reverseTransfer(int $transferId, int $userId): array
    {
        return DB::transaction(function () use ($transferId, $userId): array {
            /** @var WalletTransfer $origTransfer */
            $origTransfer = WalletTransfer::lockForUpdate()->findOrFail($transferId);

            if ($origTransfer->isReversal()) {
                throw ValidationException::withMessages([
                    'transfer_id' => ['لا يمكن عكس عملية هي بالأصل عملية عكسية.'],
                ]);
            }

            if ($origTransfer->isReversed()) {
                throw ValidationException::withMessages([
                    'transfer_id' => ['تم عكس هذه العملية مسبقاً ولا يمكن عكسها مرة أخرى.'],
                ]);
            }

            // قفل المحافظ بالترتيب الموحد دائماً: الشبكة ثم نقطة البيع
            $networkWallet = $this->networkWalletRepository->findByIdForUpdate($origTransfer->network_wallet_id);
            $networkWallet->ensureActive();

            $posWallet = $this->posWalletRepository->findByIdForUpdate($origTransfer->pos_wallet_id);
            $posWallet->ensureActive();
            $this->assertMatchingWalletCurrencies($networkWallet, $posWallet);

            $amount = (float) $origTransfer->amount;

            if ($origTransfer->direction === 'NET_TO_POS') {
                if (! $posWallet->hasSufficientBalance($amount)) {
                    throw ValidationException::withMessages([
                        'amount' => ['رصيد محفظة نقطة البيع الحالي غير كافٍ لعكس عملية التغذية.'],
                    ]);
                }

                $reversalDirection = 'POS_TO_NET';
                $reversalStatement = "عكس عملية تحويل #{$origTransfer->transfer_id}";

                $reversalTransfer = WalletTransfer::create([
                    'transfer_uuid' => (string) Str::uuid(),
                    'network_wallet_id' => $networkWallet->wallet_id,
                    'pos_wallet_id' => $posWallet->wallet_id,
                    'amount' => $amount,
                    'direction' => $reversalDirection,
                    'original_transfer_id' => $origTransfer->transfer_id,
                    'statement' => $reversalStatement,
                    'created_by' => $userId,
                ]);

                $this->posWalletRepository->decrementBalance($posWallet->wallet_id, $amount);
                $posLog = $this->posWalletLogRepository->createLog(
                    $posWallet->wallet_id,
                    WalletOperationType::WITHDRAW->value,
                    $amount,
                    $reversalStatement,
                    $userId,
                    null,
                    $reversalTransfer->transfer_id
                );

                $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $amount);
                $netLog = $this->networkWalletLogRepository->createLog(
                    $networkWallet->wallet_id,
                    WalletOperationType::DEPOSIT->value,
                    $amount,
                    $reversalStatement,
                    $userId,
                    null,
                    $reversalTransfer->transfer_id
                );
            } else {
                if (! $networkWallet->hasSufficientBalance($amount)) {
                    throw ValidationException::withMessages([
                        'amount' => ['رصيد محفظة الشبكة الحالي غير كافٍ لعكس عملية الاسترجاع.'],
                    ]);
                }

                $reversalDirection = 'NET_TO_POS';
                $reversalStatement = "عكس عملية استرجاع #{$origTransfer->transfer_id}";

                $reversalTransfer = WalletTransfer::create([
                    'transfer_uuid' => (string) Str::uuid(),
                    'network_wallet_id' => $networkWallet->wallet_id,
                    'pos_wallet_id' => $posWallet->wallet_id,
                    'amount' => $amount,
                    'direction' => $reversalDirection,
                    'original_transfer_id' => $origTransfer->transfer_id,
                    'statement' => $reversalStatement,
                    'created_by' => $userId,
                ]);

                $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, $amount);
                $netLog = $this->networkWalletLogRepository->createLog(
                    $networkWallet->wallet_id,
                    WalletOperationType::WITHDRAW->value,
                    $amount,
                    $reversalStatement,
                    $userId,
                    null,
                    $reversalTransfer->transfer_id
                );

                $this->posWalletRepository->incrementBalance($posWallet->wallet_id, $amount);
                $posLog = $this->posWalletLogRepository->createLog(
                    $posWallet->wallet_id,
                    WalletOperationType::DEPOSIT->value,
                    $amount,
                    $reversalStatement,
                    $userId,
                    null,
                    $reversalTransfer->transfer_id
                );
            }

            $origTransfer->status = 'REVERSED';
            $origTransfer->save();

            return [
                'original_transfer' => $origTransfer,
                'reversal_transfer' => $reversalTransfer->refresh(),
                'network_wallet' => $networkWallet->refresh(),
                'pos_wallet' => $posWallet->refresh(),
                'network_log' => $netLog,
                'pos_log' => $posLog,
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

    /**
     * إرسال إشعار SMS لمدير نقطة البيع عند حركات القبض أو الدفع على المحفظة.
     *
     * صيغة حركة القبض (تغذية / إيداع):
     * "نقطة {اسم_النقطة} - شبكة {اسم_الشبكة}: لكم سند قبض بمبلغ {المبلغ}YER رصيدكم {الرصيد}YER"
     *
     * صيغة حركة الدفع (سحب / استرجاع / قيد فاتورة):
     * "نقطة {اسم_النقطة} - شبكة {اسم_الشبكة}: عليكم سند دفع بمبلغ {المبلغ}YER رصيدكم {الرصيد}YER"
     *
     * @param  PosWallet  $wallet  كائن محفظة نقطة البيع
     * @param  string  $action  نوع الحركة ('receipt' للقبض والإيداع، 'payment' للدفع والسحب والقيد)
     * @param  float  $amount  مبلغ الحركة
     * @param  float|null  $currentBalance  الرصيد الفعلي بعد الحركة (إن لم يحدد يُؤخذ من المحفظة)
     */
    private function assertMatchingWalletCurrencies(NetworkWallet $networkWallet, PosWallet $posWallet): void
    {
        $networkCurrency = strtoupper(trim((string) $networkWallet->currency_code));
        $posCurrency = strtoupper(trim((string) $posWallet->currency_code));
        if (preg_match('/^[A-Z]{3}$/D', $networkCurrency) !== 1
            || preg_match('/^[A-Z]{3}$/D', $posCurrency) !== 1) {
            throw ValidationException::withMessages([
                'currency_code' => ['رمز عملة إحدى المحافظ غير صالح؛ أُوقفت الحركة المالية.'],
            ]);
        }
        if ($networkCurrency !== $posCurrency) {
            throw ValidationException::withMessages([
                'currency_code' => ['عملة محفظة الشبكة لا تطابق عملة محفظة نقطة البيع؛ لا يمكن تنفيذ تحويل بين عملتين مختلفتين.'],
            ]);
        }
    }

    public function sendWalletSms(PosWallet $wallet, string $action, float $amount, ?float $currentBalance = null): void
    {
        $wallet->loadMissing(['pos', 'network']);

        $phoneNumber = $wallet->pos->pos_phone_number;
        if (empty($phoneNumber)) {
            return;
        }

        $posName = $wallet->pos->pos_name ?? "نقطة #{$wallet->pos_id}";
        $networkName = $wallet->network->network_name ?? "شبكة #{$wallet->network_id}";

        $balance = $currentBalance ?? (float) $wallet->wallet_balance;

        $currencyCode = strtoupper(trim((string) $wallet->currency_code));
        $formattedAmount = AmountFormatter::formatWithCurrency($amount, $currencyCode);
        $formattedBalance = AmountFormatter::formatWithCurrency($balance, $currencyCode);

        $actionText = ($action === 'receipt') ? 'لكم سند قبض' : 'عليكم سند صرف';

        $message = "نقطة {$posName} - شبكة {$networkName}: {$actionText} بمبلغ {$formattedAmount} رصيدكم {$formattedBalance}";

        $this->notificationService->sendDirect(
            userId: $wallet->pos->owner_id,
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
