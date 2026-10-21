<?php

namespace App\Services;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NotificationType;
use App\Enums\SaleType;
use App\Enums\WalletOperationType;
use App\Http\Requests\API\V1\SaleInvoice\StoreSaleInvoiceRequest;
use App\Http\Requests\API\V1\SaleInvoice\UpdateSaleInvoiceRequest;
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
use App\Models\Request as RequestModel;
use App\Models\RequestItem;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
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
        protected ?NetworkPosRepositoryInterface $networkPosRepository = null
    ) {
        $this->networkPosRepository = $networkPosRepository ?? app(NetworkPosRepositoryInterface::class);
    }

    /**
     * جلب قائمة فواتير المبيعات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->saleInvoiceRepository->getFiltered($filters, ['network', 'pos', 'createdBy', 'items.voucherPackage']);
    }

    /**
     * جلب الفواتير المحذوفة مؤقتاً.
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

            if (empty($itemsSource) || (is_countable($itemsSource) && count($itemsSource) === 0)) {
                throw ValidationException::withMessages([
                    'items' => ['الطلب لا يحتوي على أي باقات أو كروت للفوترة.'],
                ]);
            }

            // 2. التحقق من محفظة الوكيل بقفل متشائم
            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            if (! $posWallet) {
                throw ValidationException::withMessages([
                    'pos_wallet' => ['لا توجد محفظة مالية لنقطة البيع لدى هذه الشبكة لإتمام السداد والفوترة.'],
                ]);
            }
            $posWallet->ensureActive();

            // 3. التحقق من محفظة الشبكة بقفل متشائم
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لإيداع مستحقات الفاتورة.'],
                ]);
            }
            $networkWallet->ensureActive();

            // 4. احتساب أسعار وخصومات الباقات بنمطي البيع بالاعتماد الصريح على دوال موديل SaleInvoiceItem المساعدة
            $cashItemsData = [];
            $cashTotal = 0.0;
            $creditItemsData = [];
            $creditTotal = 0.0;

            foreach ($itemsSource as $item) {
                if ($hasOverrideItems) {
                    $packageId = (int) $item['package_id'];
                    $voucherCount = (int) $item['voucher_count'];
                    $package = $this->voucherPackageRepository->findById($packageId);
                    $package->ensureActive();
                    if ((int) $package->network_id !== $networkId) {
                        throw ValidationException::withMessages([
                            'items' => ["الباقة ({$package->package_name}) لا تنتمي لمزود الشبكة المحدد."],
                        ]);
                    }
                    $voucherPrice = isset($item['voucher_price']) && (float) $item['voucher_price'] > 0
                        ? (float) $item['voucher_price']
                        : (float) $package->package_voucher_price;

                    $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $packageId);
                    $cashDiscPercent = isset($item['item_discount']) && $item['item_discount'] !== null
                        ? (float) $item['item_discount']
                        : (float) ($discountData['discount_cash'] ?? 0.0);
                    $creditDiscPercent = isset($item['item_discount']) && $item['item_discount'] !== null
                        ? (float) $item['item_discount']
                        : (float) ($discountData['discount_credit'] ?? 0.0);
                } else {
                    /** @var RequestItem $item */
                    $package = $item->voucherPackage ?? $this->voucherPackageRepository->findById($item->package_id);
                    $package->ensureActive();
                    $voucherCount = (int) $item->voucher_count;
                    $voucherPrice = (float) (($item->voucher_price ?? 0) > 0 ? $item->voucher_price : $package->package_voucher_price);

                    $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $package->package_id);
                    $cashDiscPercent = $item->item_discount !== null && $request->isCash()
                        ? (float) $item->item_discount
                        : (float) ($discountData['discount_cash'] ?? 0.0);
                    $creditDiscPercent = $item->item_discount !== null && $request->isCredit()
                        ? (float) $item->item_discount
                        : (float) ($discountData['discount_credit'] ?? 0.0);
                }

                // احتساب الصافي النقدي عبر دالة calculateLineTotal بالموديل
                $cashItemModel = new SaleInvoiceItem([
                    'package_id' => $package->package_id,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $voucherCount,
                    'item_discount' => $cashDiscPercent,
                ]);
                $cashNetAmount = $cashItemModel->calculateLineTotal();
                $cashTotal += $cashNetAmount;
                $cashItemsData[] = [
                    'package_id' => $package->package_id,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $voucherCount,
                    'item_discount' => $cashDiscPercent,
                    'item_total_amount' => $cashNetAmount,
                ];

                // احتساب الصافي الآجل عبر دالة calculateLineTotal بالموديل
                $creditItemModel = new SaleInvoiceItem([
                    'package_id' => $package->package_id,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $voucherCount,
                    'item_discount' => $creditDiscPercent,
                ]);
                $creditNetAmount = $creditItemModel->calculateLineTotal();
                $creditTotal += $creditNetAmount;
                $creditItemsData[] = [
                    'package_id' => $package->package_id,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $voucherCount,
                    'item_discount' => $creditDiscPercent,
                    'item_total_amount' => $creditNetAmount,
                ];
            }

            // 5. تطبيق المنطق المحاسبي للبرامج الكبرى:
            $effectiveSaleType = $overrideSaleType ?? $request->sale_type ?? SaleType::CASH;
            $isCashSale = ($effectiveSaleType === SaleType::CASH) && $posWallet->hasSufficientBalance($cashTotal);

            if ($isCashSale) {
                $saleType = SaleType::CASH;
                $totalInvoiceAmount = $cashTotal;
                $paidAmount = $cashTotal;
                $remainingAmount = 0.00;
                $paymentStatus = InvoicePaymentStatus::PAID;
                $itemsData = $cashItemsData;
            } else {
                $saleType = SaleType::CREDIT;
                $totalInvoiceAmount = $creditTotal;
                $existingCredit = max(0.0, (float) $posWallet->wallet_balance);
                $coveredAmount = min($existingCredit, $totalInvoiceAmount);
                $paidAmount = round($coveredAmount, 2);
                $remainingAmount = round($totalInvoiceAmount - $paidAmount, 2);
                $paymentStatus = ($remainingAmount <= 0.001)
                    ? InvoicePaymentStatus::PAID
                    : (($paidAmount > 0.001) ? InvoicePaymentStatus::PARTIALLY_PAID : InvoicePaymentStatus::UNPAID);
                $itemsData = $creditItemsData;
            }

            // 6. إنشاء فاتورة المبيعات الرسمية
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

            // 7. حفظ بنود الفاتورة
            $this->saleInvoiceItemRepository->createManyForInvoice($invoice->invoice_id, $itemsData);

            // 8. التسوية المالية المزدوجة المحاسبية (خصم من الوكيل وإيداع في الشبكة)
            $this->posWalletRepository->decrementBalance($posWallet->wallet_id, $totalInvoiceAmount);
            $posBalanceAfter = round((float) $posWallet->wallet_balance - $totalInvoiceAmount, 2);

            $posStatement = $isCashSale
                ? "سداد فاتورة مبيعات كروت نقدية (#{$invoice->invoice_uuid}) ناتجة عن الطلب (#{$request->request_uuid})"
                : ($paidAmount > 0.001
                    ? "سداد فاتورة كروت بالبيع الآجل (#{$invoice->invoice_uuid}) مدفوعة جزئياً من الرصيد: ({$paidAmount})، متبقي آجل: ({$remainingAmount}) - رصيد المحفظة الحالي: ({$posBalanceAfter}) ناتجة عن الطلب (#{$request->request_uuid})"
                    : "سداد فاتورة كروت بالبيع الآجل (#{$invoice->invoice_uuid}) لعدم كفاية الرصيد النقدي - رصيد مدين على الوكيل: ({$posBalanceAfter}) ناتجة عن الطلب (#{$request->request_uuid})");

            $this->posWalletLogRepository->createLog(
                $posWallet->wallet_id,
                WalletOperationType::WITHDRAW,
                $totalInvoiceAmount,
                $posStatement,
                $userId
            );

            $posWallet->refresh();
            $this->posWalletService->sendWalletSms($posWallet, 'payment', (float) $totalInvoiceAmount, $posBalanceAfter);

            $this->notificationService->sendDirect(
                userId: $userId,
                type: NotificationType::WALLET_WITHDRAWAL,
                push: true,
                title: $isCashSale
                    ? 'سداد فاتورة مبيعات كروت نقدية'
                    : ($paidAmount > 0.001
                        ? 'سداد فاتورة كروت بالبيع الآجل مدفوعة جزئياً'
                        : 'سداد فاتورة كروت بالبيع الآجل لعدم كفاية الرصيد النقدي'),
                message: $isCashSale
                    ? "سداد فاتورة مبيعات كروت نقدية (#{$invoice->invoice_uuid}) ناتجة عن الطلب (#{$request->request_uuid})"
                    : ($paidAmount > 0.001
                        ? "سداد فاتورة كروت بالبيع الآجل (#{$invoice->invoice_uuid}) مدفوعة جزئياً من الرصيد: ({$paidAmount})، متبقي آجل: ({$remainingAmount}) - رصيد المحفظة الحالي: ({$posBalanceAfter}) ناتجة عن الطلب (#{$request->request_uuid})"
                        : "سداد فاتورة كروت بالبيع الآجل (#{$invoice->invoice_uuid}) لعدم كفاية الرصيد النقدي - رصيد مدين على الوكيل: ({$posBalanceAfter}) ناتجة عن الطلب (#{$request->request_uuid})"),
            );

            $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $totalInvoiceAmount);

            $networkStatement = $isCashSale
                ? "تحصيل مبيعات كروت نقدية (#{$invoice->invoice_uuid}) من نقطة البيع (#{$pos->pos_name}) ناتجة عن الطلب (#{$request->request_uuid})"
                : ($paidAmount > 0.001
                    ? "تحصيل مبيعات كروت بالبيع الآجل (#{$invoice->invoice_uuid}) من نقطة البيع (#{$pos->pos_name}) - مسدد من الرصيد: ({$paidAmount})، آجل متبقي: ({$remainingAmount})"
                    : "تحصيل مبيعات كروت بالبيع الآجل (#{$invoice->invoice_uuid}) من نقطة البيع (#{$pos->pos_name}) ناتجة عن الطلب (#{$request->request_uuid})");

            $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::DEPOSIT,
                $totalInvoiceAmount,
                $networkStatement,
                $userId
            );

            $invoice->load(['network', 'pos', 'createdBy', 'items.voucherPackage']);

            $this->notificationService->sendDirect(
                userId: $userId,
                type: NotificationType::WALLET_DEPOSIT,
                push: true,
                title: $isCashSale
                ? 'تحصيل مبيعات كروت نقدية'
                : ($paidAmount > 0.001
                ? 'تحصيل مبيعات كروت بالبيع الآجل'
                : 'تحصيل مبيعات كروت بالبيع الآجل'),
                message: $isCashSale
                ? "تحصيل مبيعات كروت نقدية (#{$invoice->invoice_uuid}) من نقطة البيع (#{$pos->pos_name}) ناتجة عن الطلب (#{$request->request_uuid})"
                : ($paidAmount > 0.001
                ? "تحصيل مبيعات كروت بالبيع الآجل (#{$invoice->invoice_uuid}) من نقطة البيع (#{$pos->pos_name}) - مسدد من الرصيد: ({$paidAmount})، آجل متبقي: ({$remainingAmount})"
                : "تحصيل مبيعات كروت بالبيع الآجل (#{$invoice->invoice_uuid}) من نقطة البيع (#{$pos->pos_name}) ناتجة عن الطلب (#{$request->request_uuid})"),
            );

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
            $saleType = $request->getSaleType();
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
            $totalInvoiceAmount = 0.0;

            foreach ($items as $item) {
                $package = $this->voucherPackageRepository->findById($item['package_id']);
                if ((int) $package->network_id !== $networkId) {
                    throw ValidationException::withMessages([
                        'items' => ["الباقة ({$package->package_name}) لا تنتمي لمزود الشبكة المحدد."],
                    ]);
                }
                $package->ensureActive();
                $price = (float) $item['voucher_price'];
                $count = (int) $item['voucher_count'];
                $discount = isset($item['item_discount']) ? (float) $item['item_discount'] : 0.0;

                $invoiceItemModel = new SaleInvoiceItem([
                    'package_id' => $package->package_id,
                    'voucher_price' => $price,
                    'voucher_count' => $count,
                    'item_discount' => $discount,
                ]);
                $lineTotal = $invoiceItemModel->calculateLineTotal();

                $totalInvoiceAmount += $lineTotal;

                $itemsData[] = [
                    'package_id' => $package->package_id,
                    'voucher_price' => $price,
                    'voucher_count' => $count,
                    'item_discount' => $discount,
                    'item_total_amount' => $lineTotal,
                ];
            }

            // 2. التحقق من وجود ونشاط المحافظ المالية بقفل متشائم
            $posWallet = $this->posWalletRepository->findByNetworkAndPos($networkId, $posId, true);
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);

            // 3. احتساب المبالغ وحالة السداد:
            $paidAmountInput = $request->getPaidAmount();
            if ($paidAmountInput !== null) {
                $paidAmount = min($totalInvoiceAmount, max(0.0, (float) $paidAmountInput));
            } else {
                $paidAmount = ($saleType === SaleType::CASH) ? $totalInvoiceAmount : 0.00;
            }

            $remainingAmount = max(0.0, round($totalInvoiceAmount - $paidAmount, 2));

            if ($remainingAmount <= 0.001) {
                $paymentStatus = InvoicePaymentStatus::PAID;
                $saleType = SaleType::CASH;
            } elseif ($paidAmount > 0.001) {
                $paymentStatus = InvoicePaymentStatus::PARTIALLY_PAID;
                $saleType = SaleType::CREDIT;
            } else {
                $paymentStatus = InvoicePaymentStatus::UNPAID;
                $saleType = SaleType::CREDIT;
            }

            // 4. التسوية المالية في المحافظ:
            // يُخصم من محفظة نقطة البيع المبلغ المتبقي فقط كمديونية/ذمم مدينة
            if ($posWallet && $remainingAmount > 0.001) {
                $posWallet->ensureActive();
                $this->posWalletRepository->decrementBalance($posWallet->wallet_id, $remainingAmount);
                $posBalanceAfter = round((float) $posWallet->wallet_balance - $remainingAmount, 2);

                $posStatement = $paidAmount > 0.001
                    ? "سداد فاتورة مبيعات مباشرة مدفوعة جزئياً - إجمالي: ({$totalInvoiceAmount})، مسدد نقداً: ({$paidAmount})، متبقي آجل على المحفظة: ({$remainingAmount}) (رصيد مدين: {$posBalanceAfter})"
                    : "سداد فاتورة مبيعات مباشرة بالبيع الآجل - ذمم مدينة (رصيد مدين على الوكيل: {$posBalanceAfter})";

                $this->posWalletLogRepository->createLog(
                    $posWallet->wallet_id,
                    WalletOperationType::WITHDRAW,
                    $remainingAmount,
                    $posStatement,
                    $userId
                );

                $posWallet->refresh();

                $this->notificationService->sendDirect(
                    userId: $userId,
                    type: NotificationType::WALLET_WITHDRAWAL,
                    push: true,
                    title: $paidAmount > 0.001
                    ? 'سداد فاتورة مبيعات مباشرة مدفوعة جزئياً'
                    : 'سداد فاتورة مبيعات مباشرة بالبيع الآجل',
                    message: $paidAmount > 0.001
                    ? "سداد فاتورة مبيعات مباشرة مدفوعة جزئياً - إجمالي: ({$totalInvoiceAmount})، مسدد نقداً: ({$paidAmount})، متبقي آجل على المحفظة: ({$remainingAmount}) (رصيد مدين: {$posBalanceAfter})"
                    : "سداد فاتورة مبيعات مباشرة بالبيع الآجل - ذمم مدينة (رصيد مدين على الوكيل: {$posBalanceAfter})",
                );

                $this->posWalletService->sendWalletSms($posWallet, 'payment', (float) $remainingAmount, $posBalanceAfter);
            }

            if ($networkWallet) {
                $networkWallet->ensureActive();
                // إيداع إجمالي الفاتورة في محفظة الشبكة كاستحقاق مبيعات
                $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $totalInvoiceAmount);

                $networkStatement = "تحصيل فاتورة مبيعات مباشرة من نقطة البيع (#{$pos->pos_name}) - إجمالي: ({$totalInvoiceAmount})، نقد محصل: ({$paidAmount})، آجل متبقي: ({$remainingAmount})";

                $this->networkWalletLogRepository->createLog(
                    $networkWallet->wallet_id,
                    WalletOperationType::DEPOSIT,
                    $totalInvoiceAmount,
                    $networkStatement,
                    $userId
                );

                $this->notificationService->sendDirect(
                    userId: $userId,
                    type: NotificationType::WALLET_DEPOSIT,
                    push: true,
                    title: 'تحصيل فاتورة مبيعات مباشرة',
                    message: "تحصيل فاتورة مبيعات مباشرة من نقطة البيع (#{$pos->pos_name}) - إجمالي: ({$totalInvoiceAmount})، نقد محصل: ({$paidAmount})، آجل متبقي: ({$remainingAmount})",
                );
            }

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

            $invoice->load(['network', 'pos', 'createdBy', 'items.voucherPackage']);

            return $invoice;
        });
    }

    /**
     * تحديث نوع الفاتورة (كاش/آجل).
     */
    public function updateSaleType(int $invoiceId, UpdateSaleInvoiceRequest $request): SaleInvoice
    {
        return DB::transaction(function () use ($invoiceId, $request): SaleInvoice {
            $invoice = $this->saleInvoiceRepository->findById($invoiceId);

            $network = $this->networkRepository->findById($invoice->network_id);
            $network->ensureActive();

            $pos = $this->posRepository->findById($invoice->pos_id);
            $pos->ensureActive();

            $this->saleInvoiceRepository->patch($invoiceId, [
                'sale_type' => $request->getSaleType(),
            ]);

            return $this->getById($invoiceId);
        });
    }

    /**
     * حذف فاتورة مؤقتاً.
     */
    public function deleteInvoice(int $invoiceId): bool
    {
        return DB::transaction(function () use ($invoiceId): bool {
            return (bool) $this->saleInvoiceRepository->delete($invoiceId);
        });
    }

    /**
     * استعادة فاتورة محذوفة.
     */
    public function restoreInvoice(int $invoiceId): bool
    {
        return DB::transaction(function () use ($invoiceId): bool {
            return (bool) $this->saleInvoiceRepository->restore($invoiceId);
        });
    }

    /**
     * الحذف النهائي لفاتورة مبيعات.
     */
    public function forceDeleteInvoice(int $invoiceId): bool
    {
        return DB::transaction(function () use ($invoiceId): bool {
            return (bool) $this->saleInvoiceRepository->forceDelete($invoiceId);
        });
    }

    /**
     * الحذف المجمع لفواتير المبيعات.
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->saleInvoiceRepository->bulkDelete($ids);
        });
    }

    /**
     * الاستعادة المجمعة لفواتير المبيعات.
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->saleInvoiceRepository->bulkRestore($ids);
        });
    }

    /**
     * الحذف النهائي المجمع لفواتير المبيعات.
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->saleInvoiceRepository->bulkForceDelete($ids);
        });
    }
}
