<?php

namespace App\Services;

use App\Enums\InvoicePaymentStatus;
use App\Enums\PaymentType;
use App\Enums\WalletOperationType;
use App\Http\Requests\API\V1\SaleInvoice\StoreSaleInvoiceRequest;
use App\Http\Requests\API\V1\SaleInvoice\UpdateSaleInvoiceRequest;
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
use App\Models\SaleInvoice;
use App\Models\VoucherPackage;
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
        protected PosWalletService $posWalletService
    ) {}

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
     */
    public function generateFromRequest(RequestModel $request, int $userId): SaleInvoice
    {
        return DB::transaction(function () use ($request, $userId): SaleInvoice {
            $networkId = (int) $request->network_id;
            $posId = (int) $request->pos_id;

            // 1. استخراج بنود الطلب
            $requestItems = $request->items()->with('voucherPackage')->get();
            if ($requestItems->isEmpty()) {
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

            if (! $posWallet->isActive()) {
                throw ValidationException::withMessages([
                    'pos_wallet' => ['محفظة نقطة البيع معطلة أو مجمدة حالياً، لا يمكن سداد قيمة الفاتورة.'],
                ]);
            }

            // 3. التحقق من محفظة الشبكة بقفل متشائم
            $networkWallet = $this->networkWalletRepository->findByNetworkId($networkId, true);
            if (! $networkWallet) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['لم يتم العثور على محفظة مالية مسجلة للشبكة لإيداع مستحقات الفاتورة.'],
                ]);
            }

            if (! $networkWallet->isActive()) {
                throw ValidationException::withMessages([
                    'network_wallet' => ['محفظة مزود الشبكة مجمدة حالياً، لا يمكن إتمام التسوية المالية.'],
                ]);
            }

            // 4. احتساب أسعار وخصومات الباقات بنمطي البيع (النقدي والآجل)
            $cashItemsData = [];
            $cashTotal = 0.0;
            $creditItemsData = [];
            $creditTotal = 0.0;

            foreach ($requestItems as $item) {
                /** @var VoucherPackage|null $package */
                $package = $item->voucherPackage;
                if (! $package) {
                    $package = $this->voucherPackageRepository->findById($item->package_id);
                }

                $voucherPrice = (float) $package->package_voucher_price;
                $voucherCount = (int) $item->item_voucher_count;
                $grossAmount = round($voucherPrice * $voucherCount, 2);

                $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $package->package_id);

                // احتساب الخصم النقدي
                $cashDiscPercent = (float) ($discountData['discount_cash'] ?? 0.0);
                $cashDiscAmount = round($grossAmount * ($cashDiscPercent / 100), 2);
                $cashNetAmount = max(0.0, round($grossAmount - $cashDiscAmount, 2));
                $cashTotal += $cashNetAmount;
                $cashItemsData[] = [
                    'package_id' => $package->package_id,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $voucherCount,
                    'item_discount' => $cashDiscPercent,
                    'item_total_amount' => $cashNetAmount,
                ];

                // احتساب الخصم الآجل
                $creditDiscPercent = (float) ($discountData['discount_credit'] ?? 0.0);
                $creditDiscAmount = round($grossAmount * ($creditDiscPercent / 100), 2);
                $creditNetAmount = max(0.0, round($grossAmount - $creditDiscAmount, 2));
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
            // إذا كان الرصيد كافياً نقداً يُسجل البيع نقدياً (CASH).
            // إذا لم يكن كافياً، يتحول تلقائياً إلى بيع آجل (CREDIT) برصيد سالب (مدين على الوكيل).
            $isCashSale = $posWallet->hasSufficientBalance($cashTotal);

            if ($isCashSale) {
                $saleType = PaymentType::CASH;
                $totalInvoiceAmount = $cashTotal;
                $paidAmount = $cashTotal;
                $remainingAmount = 0.00;
                $paymentStatus = InvoicePaymentStatus::PAID;
                $itemsData = $cashItemsData;
            } else {
                $saleType = PaymentType::CREDIT;
                $totalInvoiceAmount = $creditTotal;
                $paidAmount = 0.00;
                $remainingAmount = $creditTotal;
                $paymentStatus = InvoicePaymentStatus::UNPAID;
                $itemsData = $creditItemsData;
            }

            // 6. إنشاء فاتورة المبيعات الرسمية
            /** @var SaleInvoice $invoice */
            $invoice = $this->saleInvoiceRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'invoice_sale_type' => $saleType,
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
                : "سداد فاتورة كروت بالبيع الآجل (#{$invoice->invoice_uuid}) لعدم كفاية الرصيد النقدي - رصيد مدين على الوكيل: ({$posBalanceAfter}) ناتجة عن الطلب (#{$request->request_uuid})";

            $this->posWalletLogRepository->createLog(
                $posWallet->wallet_id,
                WalletOperationType::WITHDRAW,
                $totalInvoiceAmount,
                $posStatement,
                $userId
            );

            $posWallet->refresh();
            $this->posWalletService->sendWalletSms($posWallet, 'payment', (float) $totalInvoiceAmount, $posBalanceAfter);

            $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $totalInvoiceAmount);

            $networkStatement = $isCashSale
                ? "تحصيل مبيعات كروت نقدية (#{$invoice->invoice_uuid}) من نقطة البيع (#{$posId}) ناتجة عن الطلب (#{$request->request_uuid})"
                : "تحصيل مبيعات كروت بالبيع الآجل (#{$invoice->invoice_uuid}) من نقطة البيع (#{$posId}) ناتجة عن الطلب (#{$request->request_uuid})";

            $this->networkWalletLogRepository->createLog(
                $networkWallet->wallet_id,
                WalletOperationType::DEPOSIT,
                $totalInvoiceAmount,
                $networkStatement,
                $userId
            );

            $invoice->load(['network', 'pos', 'createdBy', 'items.voucherPackage']);

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

            // التحقق من وجود الشبكة ونقطة البيع
            $this->networkRepository->findById($networkId);
            $this->posRepository->findById($posId);

            $itemsData = [];
            $totalInvoiceAmount = 0.0;

            foreach ($items as $item) {
                $package = $this->voucherPackageRepository->findById($item['package_id']);
                $price = (float) $item['voucher_price'];
                $count = (int) $item['voucher_count'];
                $discount = isset($item['item_discount']) ? (float) $item['item_discount'] : 0.0;

                $gross = round($price * $count, 2);
                $discAmount = round($gross * ($discount / 100), 2);
                $lineTotal = max(0.0, round($gross - $discAmount, 2));

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
                $paidAmount = ($saleType === PaymentType::CASH) ? $totalInvoiceAmount : 0.00;
            }

            $remainingAmount = max(0.0, round($totalInvoiceAmount - $paidAmount, 2));

            if ($remainingAmount <= 0.001) {
                $paymentStatus = InvoicePaymentStatus::PAID;
                $saleType = PaymentType::CASH;
            } elseif ($paidAmount > 0.001) {
                $paymentStatus = InvoicePaymentStatus::PARTIALLY_PAID;
                $saleType = PaymentType::CREDIT;
            } else {
                $paymentStatus = InvoicePaymentStatus::UNPAID;
                $saleType = PaymentType::CREDIT;
            }

            // 4. التسوية المالية في المحافظ:
            // يُخصم من محفظة نقطة البيع المبلغ المتبقي فقط كمديونية/ذمم مدينة
            if ($posWallet && $posWallet->isActive() && $remainingAmount > 0.001) {
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
                $this->posWalletService->sendWalletSms($posWallet, 'payment', (float) $remainingAmount, $posBalanceAfter);
            }

            if ($networkWallet && $networkWallet->isActive()) {
                // إيداع إجمالي الفاتورة في محفظة الشبكة كاستحقاق مبيعات
                $this->networkWalletRepository->incrementBalance($networkWallet->wallet_id, $totalInvoiceAmount);

                $networkStatement = "تحصيل فاتورة مبيعات مباشرة من نقطة البيع (#{$posId}) - إجمالي: ({$totalInvoiceAmount})، نقد محصل: ({$paidAmount})، آجل متبقي: ({$remainingAmount})";

                $this->networkWalletLogRepository->createLog(
                    $networkWallet->wallet_id,
                    WalletOperationType::DEPOSIT,
                    $totalInvoiceAmount,
                    $networkStatement,
                    $userId
                );
            }

            /** @var SaleInvoice $invoice */
            $invoice = $this->saleInvoiceRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'invoice_sale_type' => $saleType,
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
            $this->saleInvoiceRepository->findById($invoiceId);
            $this->saleInvoiceRepository->patch($invoiceId, [
                'invoice_sale_type' => $request->getSaleType(),
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
