<?php

namespace App\Services;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NotificationType;
use App\Enums\SaleType;
use App\Enums\WalletOperationType;
use App\Http\Requests\API\V1\SaleInvoice\StoreSaleInvoiceRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkWalletLogRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\PosWalletLogRepositoryInterface;
use App\Interfaces\PosWalletRepositoryInterface;
use App\Interfaces\SaleInvoiceItemRepositoryInterface;
use App\Interfaces\SaleInvoiceRepositoryInterface;
use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Models\NetworkWallet;
use App\Models\PosWallet;
use App\Models\Request as RequestModel;
use App\Models\RequestItem;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Support\DecimalAmount;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة فواتير مبيعات الكروت والتسوية المالية الآلية (Enterprise Sale Invoice Service).
 */
class SaleInvoiceService
{
    public function __construct(
        protected SaleInvoiceRepositoryInterface $saleInvoiceRepository,
        protected SaleInvoiceItemRepositoryInterface $saleInvoiceItemRepository,
        protected VoucherPackageRepositoryInterface $voucherPackageRepository,
        protected VoucherPackageDiscountService $voucherPackageDiscountService,
        protected PosWalletRepositoryInterface $posWalletRepository,
        protected NetworkWalletRepositoryInterface $networkWalletRepository,
        protected PosWalletLogRepositoryInterface $posWalletLogRepository,
        protected NetworkWalletLogRepositoryInterface $networkWalletLogRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected PosWalletService $posWalletService,
        protected NotificationService $notificationService,
        protected ?NetworkPosRepositoryInterface $networkPosRepository = null,
        protected ?RequestPricingReviewService $requestPricingReviewService = null,
        protected ?OutboxService $outboxService = null
    ) {
        $this->networkPosRepository = $networkPosRepository ?? app(NetworkPosRepositoryInterface::class);
        $this->requestPricingReviewService = $requestPricingReviewService ?? app(RequestPricingReviewService::class);
        $this->outboxService = $outboxService ?? app(OutboxService::class);
    }

    /**
     * جلب قائمة فواتير المبيعات مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SaleInvoice>|CursorPaginator<int, SaleInvoice>
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->saleInvoiceRepository->getFiltered($filters, ['network', 'pos', 'createdBy', 'items.voucherPackage']);
    }

    /**
     * جلب الفواتير المحذوفة مؤقتاً.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SaleInvoice>|CursorPaginator<int, SaleInvoice>
     */
    public function getTrashed(array $filters = []): Collection|CursorPaginator
    {
        return $this->saleInvoiceRepository->getTrashedFiltered($filters, ['network', 'pos']);
    }

    /**
     * عرض تفاصيل فاتورة مبيعات محددة.
     */
    public function getById(int $invoiceId): SaleInvoice
    {
        /** @var SaleInvoice $invoice */
        $invoice = $this->saleInvoiceRepository->findById($invoiceId);
        $invoice->loadMissing(['network', 'pos', 'createdBy', 'items.voucherPackage']);

        return $invoice;
    }

    /**
     * توليد فاتورة مبيعات رقمية آلياً من طلب معتمد مع تطبيق الخصومات والتسوية المالية اللحظية.
     * يدعم النمط المرن بالسماح بتمرير تعديلات اختيارية على نوع البيع والبنود دون تعديل سجل الطلب الأصلي.
     *
     * @param  array<int, array{package_id: int, voucher_count: int, voucher_price?: float, item_discount?: float}>|null  $overrideItems
     */
    public function generateFromRequest(
        RequestModel $request,
        int $userId,
        ?SaleType $overrideSaleType = null,
        ?array $overrideItems = null
    ): SaleInvoice {
        return DB::transaction(function () use ($request, $userId, $overrideSaleType, $overrideItems): SaleInvoice {
            if ($overrideSaleType !== null && $overrideSaleType->value !== $request->getRawOriginal('sale_type')) {
                throw ValidationException::withMessages([
                    'sale_type' => ['لا يُسمح بتعديل نوع البيع (كاش/آجل) المتفق عليه في الطلب الأصلي من قِبل الشبكة.'],
                ]);
            }

            $networkId = (int) $request->network_id;
            $posId = (int) $request->pos_id;

            // 0. التحقق من نشاط الشبكة ونقطة البيع
            $network = $request->relationLoaded('network') ? $request->network : $this->networkRepository->findById($networkId);
            $network->ensureActive();

            $pos = $request->relationLoaded('pos') ? $request->pos : $this->posRepository->findById($posId);
            $pos->ensureActive();

            // التحقق من ارتباط نقطة البيع بالشبكة وتفعيله
            $link = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
            if (! $link) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير مرتبطة بمزود الشبكة هذا.'],
                ]);
            }
            $link->ensureActive();

            // 1. استخراج بنود الفاتورة (من التعديل الاختياري أو من بنود الطلب الأصلية دون المساس بالطلب)
            $hasOverrideItems = ! empty($overrideItems);
            $itemsSource = $hasOverrideItems
            ? $overrideItems
            : $request->items()->with('voucherPackage')->get();

            if (count($itemsSource) === 0) {
                throw ValidationException::withMessages([
                    'items' => ['الطلب لا يحتوي على أي باقات.'],
                ]);
            }

            // 2. التحقق من محفظة الشبكة أولاً وفق ترتيب الأقفال العالمي الموحد لمنع التعارض الحبيبي (Deadlock Prevention: Request -> NetworkWallet -> PosWallet)
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لإيداع مستحقات الفاتورة.'],
                ]);
            }
            $networkWallet->ensureActive();

            // 3. التحقق من محفظة نقطة البيع ثانياً بقفل متشائم
            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_wallet' => ['لا توجد محفظة مالية لنقطة البيع لدى هذه الشبكة لإتمام السداد والفوترة.'],
                ]);
            }
            $posWallet->ensureActive();
            $currencyCode = $this->assertMatchingWalletCurrencies($networkWallet, $posWallet);

            // 4. احتساب أسعار وخصومات الباقات وحسم التسعير السيادي وفق نوع البيع بالأعداد الصحيحة (Minor Units)
            $effectiveSaleType = SaleType::CASH;
            $itemsData = [];
            $totalInvoiceAmountMinor = 0;

            foreach ($itemsSource as $item) {
                if ($hasOverrideItems) {
                    $packageId = (int) $item['package_id'];
                    $voucherCount = (int) $item['voucher_count'];
                    $token = $item['pricing_token'] ?? null;

                    // حسم التسعير السيادي: البند الأصلي يحتفظ بسعره وخصمه وقت الطلب، والبند الجديد يحتفظ بسعره وخصمه المجمدين (مع حظر إعادة التسعير الصامت)
                    $pricing = $this->requestPricingReviewService->resolveItemPricing(
                        $request,
                        $packageId,
                        $token,
                        $effectiveSaleType,
                        requireValidToken: true
                    );

                    $package = $pricing['package'];
                    $voucherPrice = (float) $pricing['voucher_price'];
                    $itemDiscount = (float) $pricing['item_discount'];
                } else {
                    /** @var RequestItem $item */
                    $package = $item->voucherPackage ?? $this->voucherPackageRepository->findById($item->package_id);
                    $package->ensureActive();
                    $voucherCount = (int) $item->voucher_count;
                    // الاحتفاظ بالأسعار والخصومات الملتقطة عند إنشاء الطلب الأصلي
                    $voucherPrice = (float) (($item->voucher_price ?? 0) > 0 ? $item->voucher_price : $package->package_voucher_price);
                    $itemDiscount = (float) ($item->item_discount ?? 0.0);
                }

                $itemModel = new SaleInvoiceItem([
                    'package_id' => $package->package_id,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $voucherCount,
                    'item_discount' => $itemDiscount,
                ]);
                $netAmountMinor = $itemModel->calculateLineTotalMinor();
                $netAmount = (float) DecimalAmount::fromMinorUnits($netAmountMinor);
                $totalInvoiceAmountMinor = DecimalAmount::add($totalInvoiceAmountMinor, $netAmountMinor);

                $itemsData[] = [
                    'package_id' => $package->package_id,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $voucherCount,
                    'item_discount' => $itemDiscount,
                    'item_total_amount' => $netAmount,
                ];
            }

            // 5. All POS sales are cash-only; debt and deferred settlement are not supported.
            $totalInvoiceAmount = (float) DecimalAmount::fromMinorUnits($totalInvoiceAmountMinor);
            $saleType = SaleType::CASH;
            $paidAmount = $totalInvoiceAmount;
            $remainingAmount = 0.00;
            $paymentStatus = InvoicePaymentStatus::PAID;

            // 6. إنشاء فاتورة المبيعات الرسمية
            /** @var SaleInvoice $invoice */
            $invoice = $this->saleInvoiceRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'request_id' => $request->request_id,
                'sale_type' => $saleType,
                'invoice_total_amount' => $totalInvoiceAmount,
                'paid_amount' => $totalInvoiceAmount,
                'remaining_amount' => 0.00,
                'payment_status' => $paymentStatus,
                'invoice_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);

            // 7. حفظ بنود الفاتورة
            $this->saleInvoiceItemRepository->createManyForInvoice($invoice->invoice_id, $itemsData);

            // 8. Cash sale ledger entries preserve the POS balance and record network revenue.
            $cashSaleAmountMinor = DecimalAmount::toMinorUnits($totalInvoiceAmount);
            $posBalanceBeforeMinor = DecimalAmount::toMinorUnits($posWallet->wallet_balance);
            $posBalanceAfterDebitMinor = DecimalAmount::add($posBalanceBeforeMinor, -$cashSaleAmountMinor);
            $posBalanceBefore = DecimalAmount::fromMinorUnits($posBalanceBeforeMinor);
            $posBalanceAfterDebit = DecimalAmount::fromMinorUnits($posBalanceAfterDebitMinor);
            $this->posWalletLogRepository->createLog($posWallet->wallet_id, WalletOperationType::WITHDRAW, $totalInvoiceAmount, "استحقاق فاتورة مبيعات كروت نقدية (#{$invoice->invoice_uuid}) ناتجة عن الطلب (#{$request->request_uuid})", $userId, balanceBefore: $posBalanceBefore, balanceAfter: $posBalanceAfterDebit);
            $this->posWalletLogRepository->createLog($posWallet->wallet_id, WalletOperationType::DEPOSIT, $totalInvoiceAmount, "سداد نقدي فوري للفاتورة (#{$invoice->invoice_uuid}) ناتجة عن الطلب (#{$request->request_uuid})", $userId, balanceBefore: $posBalanceAfterDebit, balanceAfter: $posBalanceBefore);

            // إيداع المبلغ المحصل فعلياً فقط في محفظة الشبكة كسيولة نقدية متاحة وقابلة للسحب
            // في البيع النقدي: كامل إجمالي الفاتورة مسدد نقداً.
            // في البيع الآجل: المبلغ المغطى فقط من رصيد المحفظة الدائن والنقد ($paidAmount)
            $networkCashCollected = $totalInvoiceAmount;

            if ($networkCashCollected > 0.001) {
                $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $networkCashCollected);

                $networkStatement = "تحصيل مبيعات كروت نقدية (#{$invoice->invoice_uuid}) من نقطة بيع (#{$pos->pos_name}) ناتجة عن الطلب (#{$request->request_uuid})";

                $this->networkWalletLogRepository->createLog(
                    $networkWallet->wallet_id,
                    WalletOperationType::DEPOSIT,
                    $networkCashCollected,
                    $networkStatement,
                    $userId
                );
            }

            // تسجيل رسالة الأحداث المالية في صندوق الإرسال اللحظي (Transactional Outbox)
            $this->outboxService->record(
                'SALE_INVOICE',
                $invoice->invoice_id,
                'INVOICE_GENERATED',
                [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_uuid' => $invoice->invoice_uuid,
                    'network_id' => $networkId,
                    'pos_id' => $posId,
                    'sale_type' => $saleType->value,
                    'invoice_total_amount' => $totalInvoiceAmount,
                    'paid_amount' => $totalInvoiceAmount,
                    'remaining_amount' => 0.00,
                    'currency_code' => $currencyCode,
                    'payment_status' => $paymentStatus->value,
                ]
            );

            $invoice->load(['network', 'pos', 'createdBy', 'items.voucherPackage']);

            $formattedTotalInvoiceAmount = formatWithCurrency($totalInvoiceAmount);

            // تحديد المستلمين الصحيحين للإشعارات (صاحب نقطة البيع وصاحب الشبكة)
            $posRecipientId = (int) ($pos->owner_id ?? $request->created_by);
            $networkRecipientId = (int) ($network->owner_id ?? $userId);

            // Dispatch cash-sale notifications only after the financial transaction commits.
            DB::afterCommit(function () use (
                $posRecipientId,
                $networkRecipientId,
                $invoice,
                $request,
                $pos
            ): void {
                if ($posRecipientId) {
                    $this->notificationService->sendDirect(
                        userId: $posRecipientId,
                        type: NotificationType::WALLET_WITHDRAWAL,
                        push: true,
                        title: 'سداد فاتورة مبيعات كروت نقدية',
                        message: "سداد فاتورة مبيعات كروت نقدية (#{$invoice->invoice_uuid}) ناتجة عن الطلب (#{$request->request_uuid})",
                    );
                }

                if ($networkRecipientId) {
                    $this->notificationService->sendDirect(
                        userId: $networkRecipientId,
                        type: NotificationType::WALLET_DEPOSIT,
                        push: true,
                        title: 'تحصيل مبيعات كروت نقدية',
                        message: "تحصيل مبيعات كروت نقدية (#{$invoice->invoice_uuid}) من نقطة بيع (#{$pos->pos_name}) ناتجة عن الطلب (#{$request->request_uuid})",
                    );
                }
            });

            return $invoice;
        });
    }

    /**
     * إنشاء فاتورة مبيعات مباشرة (كاش أو آجل).
     */
    public function createDirectInvoice(StoreSaleInvoiceRequest $request, int $userId): SaleInvoice
    {
        return DB::transaction(function () use ($request, $userId): SaleInvoice {
            $networkId = $request->getNetworkId();
            $posId = $request->getPosId();
            $saleType = SaleType::CASH;
            $items = $request->getItems();

            // 1. التحقق من وجود الشبكة ونقطة البيع ونشاطهما
            $network = $this->networkRepository->findById($networkId);
            $network->ensureActive();

            $pos = $this->posRepository->findById($posId);
            $pos->ensureActive();

            // التحقق من ارتباط نقطة البيع بالشبكة وتفعيله
            $link = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
            if (! $link) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير مرتبطة بمزود الشبكة هذا.'],
                ]);
            }
            $link->ensureActive();

            $itemsData = [];
            $totalInvoiceAmountMinor = 0;

            foreach ($items as $item) {
                $package = $this->voucherPackageRepository->findById($item['package_id']);
                if ((int) $package->network_id !== $networkId) {
                    throw ValidationException::withMessages([
                        'items' => ["الباقة ({$package->package_name}) لا تنتمي لمزود الشبكة المحدد."],
                    ]);
                }
                $package->ensureActive();
                // SECURITY: use catalog price & server effective discount (ignore client inputs)
                $price = (float) $package->package_voucher_price;
                $count = (int) $item['voucher_count'];
                $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $package->package_id);
                $discount = $discountData['discount_cash'];

                $invoiceItemModel = new SaleInvoiceItem([
                    'package_id' => $package->package_id,
                    'voucher_price' => $price,
                    'voucher_count' => $count,
                    'item_discount' => $discount,
                ]);
                $lineTotalMinor = $invoiceItemModel->calculateLineTotalMinor();
                $lineTotal = (float) DecimalAmount::fromMinorUnits($lineTotalMinor);

                $totalInvoiceAmountMinor = DecimalAmount::add($totalInvoiceAmountMinor, $lineTotalMinor);

                $itemsData[] = [
                    'package_id' => $package->package_id,
                    'voucher_price' => $price,
                    'voucher_count' => $count,
                    'item_discount' => $discount,
                    'item_total_amount' => $lineTotal,
                ];
            }

            // 2. التحقق من وجود ونشاط المحافظ المالية وفق ترتيب الأقفال العالمي (NetworkWallet -> PosWallet)
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
                    'pos_id' => ['لم يتم العثور على محفظة مالية لنقطة البيع المرتبطة بهذه الشبكة.'],
                ]);
            }
            $posWallet->ensureActive();
            $currencyCode = $this->assertMatchingWalletCurrencies($networkWallet, $posWallet);

            // 3. Direct POS sales are cash-only; no credit or unpaid balance is created.
            $totalInvoiceAmount = (float) DecimalAmount::fromMinorUnits($totalInvoiceAmountMinor);
            $paidAmount = $totalInvoiceAmount;
            $remainingAmount = 0.00;
            $paymentStatus = InvoicePaymentStatus::PAID;

            // 4. إنشاء فاتورة المبيعات الرسمية
            /** @var SaleInvoice $invoice */
            $invoice = $this->saleInvoiceRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'sale_type' => $saleType,
                'invoice_total_amount' => $totalInvoiceAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $paymentStatus,
                'invoice_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);

            $this->saleInvoiceItemRepository->createManyForInvoice($invoice->invoice_id, $itemsData);

            // 5. Cash sale ledger entries preserve the POS balance and credit network revenue.
            $cashSaleAmountMinor = DecimalAmount::toMinorUnits($totalInvoiceAmount);
            $posBalanceBeforeMinor = DecimalAmount::toMinorUnits($posWallet->wallet_balance);
            $posBalanceAfterDebitMinor = DecimalAmount::add($posBalanceBeforeMinor, -$cashSaleAmountMinor);
            $posBalanceBefore = DecimalAmount::fromMinorUnits($posBalanceBeforeMinor);
            $posBalanceAfterDebit = DecimalAmount::fromMinorUnits($posBalanceAfterDebitMinor);
            $this->posWalletLogRepository->createLog($posWallet->wallet_id, WalletOperationType::WITHDRAW, $totalInvoiceAmount, "استحقاق فاتورة مبيعات مباشرة نقدية (#{$invoice->invoice_uuid})", $userId, balanceBefore: $posBalanceBefore, balanceAfter: $posBalanceAfterDebit);
            $this->posWalletLogRepository->createLog($posWallet->wallet_id, WalletOperationType::DEPOSIT, $totalInvoiceAmount, "سداد نقدي كاش للفاتورة المباشرة (#{$invoice->invoice_uuid})", $userId, balanceBefore: $posBalanceAfterDebit, balanceAfter: $posBalanceBefore);
            $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $totalInvoiceAmount);
            $this->networkWalletLogRepository->createLog($networkWallet->wallet_id, WalletOperationType::DEPOSIT, $totalInvoiceAmount, "تحصيل مبيعات كروت مباشرة نقدية (#{$invoice->invoice_uuid}) من نقطة بيع (#{$pos->pos_name})", $userId);

            // تسجيل رسالة الأحداث المالية في صندوق الإرسال اللحظي (Transactional Outbox)
            $this->outboxService->record(
                'SALE_INVOICE',
                $invoice->invoice_id,
                'DIRECT_INVOICE_GENERATED',
                [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_uuid' => $invoice->invoice_uuid,
                    'network_id' => $networkId,
                    'pos_id' => $posId,
                    'sale_type' => $saleType->value,
                    'invoice_total_amount' => $totalInvoiceAmount,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'payment_status' => $paymentStatus->value,
                ]
            );

            $invoice->load(['network', 'pos', 'createdBy', 'items.voucherPackage']);

            $formattedTotalInvoiceAmount = formatWithCurrency($totalInvoiceAmount);

            $posRecipientId = (int) ($pos->owner_id ?? $userId);
            $networkRecipientId = (int) ($network->owner_id ?? $userId);

            DB::afterCommit(function () use (
                $posRecipientId,
                $networkRecipientId,
                $invoice,
                $pos,
                $formattedTotalInvoiceAmount
            ): void {
                if ($posRecipientId) {
                    $this->notificationService->sendDirect(
                        userId: $posRecipientId,
                        type: NotificationType::WALLET_WITHDRAWAL,
                        push: true,
                        title: 'إصدار فاتورة مبيعات مباشرة نقدية',
                        message: "تم إصدار فاتورة مبيعات مباشرة نقدية (#{$invoice->invoice_uuid}) بقيمة ({$formattedTotalInvoiceAmount})",
                    );
                }

                if ($networkRecipientId) {
                    $this->notificationService->sendDirect(
                        userId: $networkRecipientId,
                        type: NotificationType::WALLET_DEPOSIT,
                        push: true,
                        title: 'تحصيل فاتورة مبيعات مباشرة نقدية',
                        message: "تحصيل فاتورة مبيعات مباشرة نقدية من نقطة بيع (#{$pos->pos_name}) بقيمة ({$formattedTotalInvoiceAmount})",
                    );
                }
            });

            return $invoice;
        });
    }

    private function assertMatchingWalletCurrencies(NetworkWallet $networkWallet, PosWallet $posWallet): string
    {
        $networkCurrency = strtoupper(trim((string) $networkWallet->currency_code));
        $posCurrency = strtoupper(trim((string) $posWallet->currency_code));

        if (preg_match('/^[A-Z]{3}$/D', $networkCurrency) !== 1
            || preg_match('/^[A-Z]{3}$/D', $posCurrency) !== 1
            || $networkCurrency !== $posCurrency) {
            throw ValidationException::withMessages([
                'currency_code' => ['عملة محفظة الشبكة لا تطابق عملة محفظة نقطة البيع أو أن رمز العملة غير صالح؛ أُوقفت الفاتورة دون أي تسوية.'],
            ]);
        }

        return $networkCurrency;
    }

    /**
     * حذف فاتورة مبيعات مؤقتاً مع عكس الأثر المالي كاملاً في المحافظ ودفتر الأستاذ (Financial Reversal).
     */
    public function deleteInvoice(int $invoiceId): bool
    {
        return DB::transaction(function () use ($invoiceId): bool {
            /** @var SaleInvoice $invoice */
            $invoice = SaleInvoice::query()->lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->trashed()) {
                return true;
            }

            $networkWallet = $this->networkWalletRepository->findByNetworkId($invoice->network_id, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لم يتم العثور على محفظة الشبكة لعكس أثر الفاتورة الملغاة.'],
                ]);
            }
            $networkWallet->ensureActive();

            $posWallet = $this->posWalletRepository->findByNetworkAndPos($invoice->network_id, $invoice->pos_id, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_wallet' => ['لم يتم العثور على محفظة نقطة البيع لعكس أثر الفاتورة الملغاة.'],
                ]);
            }
            $posWallet->ensureActive();
            $this->assertMatchingWalletCurrencies($networkWallet, $posWallet);

            $totalAmountMinor = DecimalAmount::toMinorUnits($invoice->invoice_total_amount);
            $currentUserId = auth()->id() ?? $invoice->created_by;
            $networkBalanceMinor = DecimalAmount::toMinorUnits($networkWallet->wallet_balance);
            if ($networkBalanceMinor < $totalAmountMinor) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لا يمكن حذف الفاتورة لعدم توفر رصيد كافٍ في محفظة الشبكة لعكس التحصيل النقدي.'],
                ]);
            }
            $this->networkWalletRepository->decrementBalance($networkWallet->wallet_id, (float) DecimalAmount::fromMinorUnits($totalAmountMinor));
            $this->networkWalletLogRepository->createLog($networkWallet->wallet_id, WalletOperationType::WITHDRAW, (float) DecimalAmount::fromMinorUnits($totalAmountMinor), "عكس تحصيل نقدية فاتورة مبيعات ملغاة (#{$invoice->invoice_uuid})", $currentUserId);

            // تسجيل الحدث في الـ Outbox
            $this->outboxService->record(
                'SALE_INVOICE',
                $invoice->invoice_id,
                'INVOICE_CANCELLED_AND_FINANCIALLY_REVERSED',
                [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_uuid' => $invoice->invoice_uuid,
                    'network_id' => $invoice->network_id,
                    'pos_id' => $invoice->pos_id,
                    'total_amount' => (float) DecimalAmount::fromMinorUnits($totalAmountMinor),
                    'sale_type' => $saleType->value,
                ]
            );

            return (bool) $this->saleInvoiceRepository->delete($invoiceId);
        });
    }

    /**
     * استعادة فاتورة محذوفة.
     */
    public function restoreInvoice(int $invoiceId): bool
    {
        // حماية محاسبية صارمة: الفاتورة التي تم إلغاؤها وعكس قيودها المالية لا يمكن إعادتها للحياة لتفادي اختلال الأرصدة.
        throw ValidationException::withMessages([
            'invoice' => ['لا يمكن استعادة فاتورة تم إلغاؤها وعكس قيودها المالية مسبقاً لمنع التضارب المحاسبي؛ يرجى إنشاء فاتورة جديدة بدلاً منها.'],
        ]);
    }

    /**
     * الحذف النهائي لفاتورة مبيعات مع ضمان عكس الأثر المالي قبل الحذف.
     */
    public function forceDeleteInvoice(int $invoiceId): bool
    {
        return DB::transaction(function () use ($invoiceId): bool {
            /** @var SaleInvoice|null $invoice */
            $invoice = SaleInvoice::withTrashed()->lockForUpdate()->find($invoiceId);
            if (! $invoice) {
                return false;
            }

            // إذا لم تكن محذوفة مؤقتاً، يتم عكس أثرها المالي أولاً قبل الحذف النهائي
            if (! $invoice->trashed()) {
                $this->deleteInvoice($invoiceId);
            }

            return (bool) $this->saleInvoiceRepository->forceDelete($invoiceId);
        });
    }

    /**
     * الحذف المجمع لفواتير المبيعات مع العكس المالي لكل فاتورة.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $count = 0;
            foreach ($ids as $id) {
                if ($this->deleteInvoice((int) $id)) {
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * الاستعادة المجمعة لفواتير المبيعات (محظورة حمايةً للأرصدة).
     *
     * @param  array<int, int>  $ids
     */
    public function bulkRestore(array $ids): int
    {
        throw ValidationException::withMessages([
            'ids' => ['لا يمكن استعادة فواتير تم إلغاؤها وعكس قيودها المالية مسبقاً لمنع التضارب المحاسبي.'],
        ]);
    }

    /**
     * الحذف النهائي المجمع لفواتير المبيعات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $count = 0;
            foreach ($ids as $id) {
                if ($this->forceDeleteInvoice((int) $id)) {
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * اجمالي مبيعات الكروت مجمعة بفترة زمنية (سلسلة زمنية حقيقية).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{period: string, total: float, count: int}>
     */
    public function getSalesTrend(array $filters = []): array
    {
        $period = (string) ($filters['period'] ?? 'daily');
        $allowed = ['daily', 'weekly', 'monthly'];
        if (! in_array($period, $allowed, true)) {
            $period = 'daily';
        }

        $query = SaleInvoice::query()->toBase();

        if (array_key_exists('authorized_network_ids', $filters) || array_key_exists('authorized_pos_ids', $filters)) {
            $networkIds = array_map('intval', (array) ($filters['authorized_network_ids'] ?? []));
            $posIds = array_map('intval', (array) ($filters['authorized_pos_ids'] ?? []));
            $query->where(function ($scope) use ($networkIds, $posIds): void {
                if ($networkIds !== []) {
                    $scope->whereIn('network_id', $networkIds);
                }
                if ($posIds !== []) {
                    $method = $networkIds === [] ? 'whereIn' : 'orWhereIn';
                    $scope->{$method}('pos_id', $posIds);
                }
            });
        }

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }
        if (! empty($filters['pos_id'])) {
            $query->where('pos_id', (int) $filters['pos_id']);
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        switch ($period) {
            case 'weekly':
                $groupExpr = "DATE_FORMAT(invoice_date, '%x-W%v')";
                break;
            case 'monthly':
                $groupExpr = "DATE_FORMAT(invoice_date, '%Y-%m')";
                break;
            default:
                $groupExpr = 'DATE(invoice_date)';
                break;
        }

        $rows = $query
            ->selectRaw("{$groupExpr} as period_label, SUM(invoice_total_amount) as total, COUNT(*) as cnt")
            ->groupBy('period_label')
            ->orderBy('period_label')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'period' => (string) $row->period_label,
                'total' => (float) $row->total,
                'count' => (int) $row->cnt,
            ];
        }

        return $result;
    }
}
