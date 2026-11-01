<?php

namespace App\Services;

use App\Enums\SaleType;
use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Models\Request as RequestModel;
use App\Models\RequestItem;
use App\Models\VoucherPackage;
use Illuminate\Validation\ValidationException;

/**
 * خدمة التسعير السيادي ومراجعة بنود طلبات التوريد (Enterprise Request Pricing Review Service).
 *
 * تتولى:
 * 1. الحفاظ الصارم على أسعار وخصومات البنود الأصلية من تاريخ تقديم الطلب (Submission Time).
 * 2. التقاط وتجميد أسعار وخصومات البنود الجديدة أو المستبدلة لحظة إضافتها للمراجعة عبر بصمات HMAC مشفرة.
 * 3. منع أي تلاعب أو تعديل يدوي للأسعار والخصومات من قبل واجهات العميل.
 * 4. احتساب مجاميع المعاينة (Preview) دون إنشاء فواتير أو تعديل الطلب الأصلي أو المساس بالأرصدة.
 */
class RequestPricingReviewService
{
    public function __construct(
        protected VoucherPackageRepositoryInterface $voucherPackageRepository,
        protected VoucherPackageDiscountService $voucherPackageDiscountService
    ) {}

    /**
     * توليد رمز توثيق تسعيري مشفر وموقع ببصمة HMAC لتجميد سعر وخصم الباقة المضافة.
     */
    public function generatePricingToken(int $requestId, int $packageId, float $price, float $discount): string
    {
        $payload = [
            'rid' => $requestId,
            'pid' => $packageId,
            'pr' => round($price, 2),
            'dc' => round($discount, 2),
            'ts' => time(),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', (string) $json, (string) config('app.key'));
        $packet = json_encode(['d' => $payload, 's' => $sig], JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode((string) $packet), '+/', '-_'), '=');
    }

    /**
     * التحقق من صحة وبصمة رمز التسعير المجمد للباقة المضافة أثناء المراجعة.
     *
     * @return array{price: float, discount: float}|null
     */
    public function verifyPricingToken(int $requestId, int $packageId, ?string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $packet = json_decode($decoded, true);
        if (! is_array($packet) || ! isset($packet['d'], $packet['s'])) {
            return null;
        }

        $payload = $packet['d'];
        $sig = $packet['s'];

        $expectedSig = hash_hmac('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES), (string) config('app.key'));
        if (! hash_equals($expectedSig, (string) $sig)) {
            return null;
        }

        // مطابقة المعرفات وحداثة الرمز (خلال 7 أيام من بدء المراجعة)
        if (
            (int) ($payload['rid'] ?? 0) !== $requestId ||
            (int) ($payload['pid'] ?? 0) !== $packageId ||
            (time() - (int) ($payload['ts'] ?? 0)) > (7 * 86400)
        ) {
            return null;
        }

        return [
            'price' => (float) $payload['pr'],
            'discount' => (float) $payload['dc'],
        ];
    }

    /**
     * تحديد وحسم التسعير السيادي للبند بناءً على القواعد الصارمة:
     * - البند الأصلي: يستخدم سعره وخصمه المخزنين في تاريخ تقديم الطلب.
     * - البند المضاف: يستخدم السعر والخصم الملتقطين والمجمدين عبر الرمز أو يلتقطهما لحظياً من الكتالوج.
     *
     * @return array{
     *     package: VoucherPackage,
     *     voucher_price: float,
     *     item_discount: float,
     *     source: string,
     *     pricing_token: ?string,
     *     original_voucher_count: ?int
     * }
     */
    public function resolveItemPricing(
        RequestModel $request,
        int $packageId,
        ?string $token = null,
        ?SaleType $effectiveSaleType = null
    ): array {
        $networkId = (int) $request->network_id;
        $posId = (int) $request->pos_id;
        $saleType = $effectiveSaleType ?? $request->sale_type ?? SaleType::CASH;

        // التحقق من الباقة وتبعية الشبكة ونشاطها
        $package = $this->voucherPackageRepository->findById($packageId);
        if (! $package || (int) $package->network_id !== $networkId) {
            throw ValidationException::withMessages([
                'items' => ["الباقة رقم [{$packageId}] لا تنتمي لمزود الشبكة المحدد."],
            ]);
        }
        $package->ensureActive();

        // 1. فحص ما إذا كان البند موجوداً في الطلب الأصلي
        $originalItems = $request->relationLoaded('items')
            ? $request->items
            : $request->items()->get();

        /** @var RequestItem|null $originalItem */
        $originalItem = $originalItems->firstWhere('package_id', $packageId);

        if ($originalItem !== null) {
            // قاعدة 1: الاحتفاظ التام بسعر البند المخزن وقت تقديم الطلب الأصلي
            $voucherPrice = (float) $originalItem->voucher_price;

            // إذا كان نمط البيع مطابقاً للطلب الأصلي، نستخدم الخصم المخزن في البند الأصلي
            if ($saleType === $request->sale_type) {
                $itemDiscount = (float) $originalItem->item_discount;
            } else {
                // إذا قام مراجع الشبكة بتعديل نمط البيع (مثلاً من نقد إلى آجل)، يطبق خصم النمط الجديد
                $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $packageId);
                $itemDiscount = $saleType->isCash()
                    ? (float) ($discountData['discount_cash'] ?? 0.0)
                    : (float) ($discountData['discount_credit'] ?? 0.0);
            }

            return [
                'package' => $package,
                'voucher_price' => $voucherPrice,
                'item_discount' => $itemDiscount,
                'source' => 'ORIGINAL',
                'pricing_token' => null,
                'original_voucher_count' => (int) $originalItem->voucher_count,
            ];
        }

        // 2. البند جديد أو مستبدل (NEW / REPLACEMENT)
        // فحص الرمز المجمد مسبقاً إذا كان ممرراً وصحيحاً
        $verified = $this->verifyPricingToken($request->request_id, $packageId, $token);

        if ($verified !== null) {
            // قاعدة 2: استخدام السعر والخصم الملتقطين لحظة إضافته للمراجعة وعدم تغييرهما إذا تغير الكتالوج
            $voucherPrice = $verified['price'];
            $itemDiscount = $verified['discount'];
            $pricingToken = $token;
        } else {
            // التقاط السعر والخصم لأول مرة من محرك التسعير والكتالوج الحالي
            $voucherPrice = (float) $package->package_voucher_price;
            $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $packageId);
            $itemDiscount = $saleType->isCash()
                ? (float) ($discountData['discount_cash'] ?? 0.0)
                : (float) ($discountData['discount_credit'] ?? 0.0);

            // تجميده فورياً برمز توثيق مشفر
            $pricingToken = $this->generatePricingToken($request->request_id, $packageId, $voucherPrice, $itemDiscount);
        }

        return [
            'package' => $package,
            'voucher_price' => $voucherPrice,
            'item_discount' => $itemDiscount,
            'source' => 'REVIEW_CAPTURED',
            'pricing_token' => $pricingToken,
            'original_voucher_count' => null,
        ];
    }

    /**
     * احتساب معاينة التعديلات والتسعير بدقة متناهية (Preview Calculation).
     *
     * @param  array<int, array{package_id: int, voucher_count: int, pricing_token?: string|null}>  $requestedItems
     * @return array<string, mixed>
     */
    public function calculatePreview(
        RequestModel $request,
        array $requestedItems,
        ?SaleType $overrideSaleType = null
    ): array {
        $effectiveSaleType = $overrideSaleType ?? $request->sale_type ?? SaleType::CASH;
        $originalItems = $request->relationLoaded('items')
            ? $request->items
            : $request->items()->with('voucherPackage')->get();

        $previewItems = [];
        $totalPreviewAmount = 0.0;
        $totalGrossAmount = 0.0;
        $processedPackageIds = [];
        $hasModifications = false;

        if ($effectiveSaleType !== $request->sale_type) {
            $hasModifications = true;
        }

        foreach ($requestedItems as $itemInput) {
            $packageId = (int) $itemInput['package_id'];
            $voucherCount = (int) $itemInput['voucher_count'];
            $token = $itemInput['pricing_token'] ?? null;

            if ($voucherCount < 1) {
                throw ValidationException::withMessages([
                    'items' => ["الكمية المطلوبة للباقة [{$packageId}] يجب أن تكون كرت واحد على الأقل."],
                ]);
            }

            $pricing = $this->resolveItemPricing($request, $packageId, $token, $effectiveSaleType);
            $package = $pricing['package'];
            $voucherPrice = $pricing['voucher_price'];
            $discountPercent = $pricing['item_discount'];

            // احتساب إجمالي السطر بدقة
            $grossLine = $voucherCount * $voucherPrice;
            $netLine = round($grossLine * (1.0 - ($discountPercent / 100.0)), 2);

            $totalGrossAmount += $grossLine;
            $totalPreviewAmount += $netLine;
            $processedPackageIds[] = $packageId;

            $changeType = 'UNCHANGED';
            if ($pricing['source'] === 'REVIEW_CAPTURED') {
                $changeType = 'ADDED';
                $hasModifications = true;
            } elseif ($pricing['original_voucher_count'] !== null && $pricing['original_voucher_count'] !== $voucherCount) {
                $changeType = 'QUANTITY_CHANGED';
                $hasModifications = true;
            }

            $previewItems[] = [
                'package_id' => $packageId,
                'package_name' => $package->package_name,
                'package_category' => $package->package_category ?? null,
                'voucher_count' => $voucherCount,
                'voucher_price' => $voucherPrice,
                'item_discount' => $discountPercent,
                'item_total_amount' => $netLine,
                'gross_total_amount' => round($grossLine, 2),
                'discount_amount' => round($grossLine - $netLine, 2),
                'source' => $pricing['source'],
                'pricing_token' => $pricing['pricing_token'],
                'change_type' => $changeType,
                'original_voucher_count' => $pricing['original_voucher_count'],
            ];
        }

        // رصد البنود الأصلية التي تم حذفها
        $removedItems = [];
        foreach ($originalItems as $orig) {
            if (! in_array((int) $orig->package_id, $processedPackageIds, true)) {
                $hasModifications = true;
                $origPkg = $orig->voucherPackage;
                $removedItems[] = [
                    'package_id' => (int) $orig->package_id,
                    'package_name' => $origPkg?->package_name ?? "باقة #{$orig->package_id}",
                    'original_voucher_count' => (int) $orig->voucher_count,
                    'voucher_price' => (float) $orig->voucher_price,
                    'item_discount' => (float) $orig->item_discount,
                    'original_item_total' => (float) $orig->item_total_amount,
                ];
            }
        }

        return [
            'request_id' => $request->request_id,
            'request_uuid' => $request->request_uuid,
            'original_total_amount' => (float) $request->request_total_amount,
            'preview_total_amount' => round($totalPreviewAmount, 2),
            'gross_total_amount' => round($totalGrossAmount, 2),
            'total_discount_amount' => round($totalGrossAmount - $totalPreviewAmount, 2),
            'sale_type' => $effectiveSaleType->value,
            'sale_type_label' => $effectiveSaleType->label(),
            'has_modifications' => $hasModifications,
            'items' => $previewItems,
            'removed_items' => $removedItems,
        ];
    }
}
