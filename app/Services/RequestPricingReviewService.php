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
     * يربط الرمز سياق التسعير الكامل: معرف الطلب، الشبكة، نقطة البيع، الباقة، ونمط البيع (نقد/آجل).
     */
    public function generatePricingToken(
        int $requestId,
        int $networkId,
        int $posId,
        int $packageId,
        SaleType $saleType,
        float $price,
        float $discount
    ): string {
        $payload = [
            'v' => 1,
            'rid' => $requestId,
            'nid' => $networkId,
            'pos' => $posId,
            'pid' => $packageId,
            'st' => $saleType->value,
            'pr' => round($price, 2),
            'dc' => round($discount, 2),
            'ts' => now('Asia/Aden')->timestamp,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', (string) $json, (string) config('app.key'));
        $packet = json_encode(['d' => $payload, 's' => $sig], JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode((string) $packet), '+/', '-_'), '=');
    }

    /**
     * التحقق من صحة وبصمة رمز التسعير المجمد للباقة المضافة أثناء المراجعة.
     * يتحقق قطيعاً من سلامة التوقيع، عدم التلاعب، ومطابقة سياق الطلب ونمط البيع، وعدم انتهاء الصلاحية، ورفض تواريخ المستقبل.
     *
     * @return array{price: float, discount: float}|null
     */
    public function verifyPricingToken(
        int $requestId,
        int $networkId,
        int $posId,
        int $packageId,
        SaleType $saleType,
        ?string $token,
        int $maxLifetimeSeconds = 604800 // 7 days
    ): ?array {
        if (empty($token) || ! is_string($token)) {
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

        if (! is_array($payload) || ! is_string($sig)) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES), (string) config('app.key'));
        if (! hash_equals($expectedSig, $sig)) {
            return null;
        }

        // التحقق من رقم إصدار الرمز (Version 1 مطلوب بدقة)
        if (! isset($payload['v']) || ! is_int($payload['v']) || $payload['v'] !== 1) {
            return null;
        }

        // مطابقة المعرفات وسياق التسعير الكامل
        if (
            (int) ($payload['rid'] ?? 0) !== $requestId ||
            (int) ($payload['nid'] ?? 0) !== $networkId ||
            (int) ($payload['pos'] ?? 0) !== $posId ||
            (int) ($payload['pid'] ?? 0) !== $packageId ||
            (string) ($payload['st'] ?? '') !== $saleType->value
        ) {
            return null;
        }

        // التحقق من صحة وصلاحية الأرقام والنسب
        if (! isset($payload['pr'], $payload['dc'], $payload['ts'])) {
            return null;
        }

        if (! is_numeric($payload['pr']) || ! is_numeric($payload['dc']) || ! is_numeric($payload['ts'])) {
            return null;
        }

        $price = (float) $payload['pr'];
        $discount = (float) $payload['dc'];
        $timestamp = (int) $payload['ts'];
        $now = now('Asia/Aden')->timestamp;

        if ($price <= 0 || $discount < 0 || $discount > 100) {
            return null;
        }

        // رفض الرموز المصدرة في المستقبل (مع سماحية 60 ثانية لتفاوت الساعات)
        if ($timestamp > ($now + 60)) {
            return null;
        }

        // رفض الرموز منتهية الصلاحية
        if (($now - $timestamp) > $maxLifetimeSeconds) {
            return null;
        }

        return [
            'price' => $price,
            'discount' => $discount,
        ];
    }

    /**
     * تحديد وحسم التسعير السيادي للبند بناءً على القواعد الصارمة:
     * - البند الأصلي: يستخدم سعره وخصمه المخزنين في تاريخ تقديم الطلب.
     * - البند المضاف: يستخدم السعر والخصم الملتقطين والمجمدين عبر الرمز الموثق، ويحظر إعادة التسعير الصامت عند الاعتماد.
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
        ?SaleType $effectiveSaleType = null,
        bool $requireValidToken = false
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
                // إذا تم تعديل نمط البيع، نستخدم لقطة الخصم المجمدة المخزنة في البند لحظة إنشاء الطلب (Zero Retrospective Requery)
                $itemDiscount = $saleType->isCash()
                    ? (float) ($originalItem->item_cash_discount ?? $originalItem->item_discount)
                    : (float) ($originalItem->item_credit_discount ?? $originalItem->item_discount);
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
        $verified = $this->verifyPricingToken(
            $request->request_id,
            $networkId,
            $posId,
            $packageId,
            $saleType,
            $token
        );

        if ($token !== null && $token !== '') {
            // في حال تم تمرير رمز تسعير مسبق لكنه غير صالح أو منتهي أو متلاعب به، نرفض العملية فوراً حتى في المعاينة (منع إعادة التسعير الصامتة)
            if ($verified === null) {
                throw ValidationException::withMessages([
                    'items' => ["رمز التسعير للباقة [{$package->package_name}] غير صالح أو منتهي الصلاحية أو تم التلاعب به. يرجى طلب معاينة تسعير جديدة."],
                ]);
            }

            // استخدام السعر والخصم المعتمدين والمجمدين بالرمز
            $voucherPrice = $verified['price'];
            $itemDiscount = $verified['discount'];
            $pricingToken = $token;
        } else {
            // في حال عدم تمرير رمز، يجب رفض الاعتماد إذا كان يتطلب توكناً صالحاً
            if ($requireValidToken) {
                throw ValidationException::withMessages([
                    'items' => ["الباقة المضافة [{$package->package_name}] تفتقر لرمز تسعير معتمد وموقع. يرجى إعادة معاينة الطلب وتأكيده."],
                ]);
            }

            // التقاط السعر والخصم لأول مرة من محرك التسعير والكتالوج الحالي (Initial Preview)
            $voucherPrice = (float) $package->package_voucher_price;
            $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $packageId);
            $itemDiscount = $saleType->isCash()
                ? (float) ($discountData['discount_cash'] ?? 0.0)
                : (float) ($discountData['discount_credit'] ?? 0.0);

            // تجميده فورياً برمز توثيق مشفر يربط كامل سياق التسعير
            $pricingToken = $this->generatePricingToken(
                $request->request_id,
                $networkId,
                $posId,
                $packageId,
                $saleType,
                $voucherPrice,
                $itemDiscount
            );
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
        if ($overrideSaleType !== null && $overrideSaleType !== $request->sale_type) {
            throw ValidationException::withMessages([
                'sale_type' => ['لا يُسمح بتعديل نوع البيع (كاش/آجل) المتفق عليه في الطلب الأصلي من قِبل الشبكة.'],
            ]);
        }

        $effectiveSaleType = $request->sale_type ?? SaleType::CASH;
        $originalItems = $request->relationLoaded('items')
            ? $request->items
            : $request->items()->with('voucherPackage')->get();

        $previewItems = [];
        $totalPreviewAmount = 0.0;
        $totalGrossAmount = 0.0;
        $processedPackageIds = [];
        $hasModifications = false;

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

    /**
     * احتساب عرض أسعار سيادي معتمد لطلب توريد جديد مع توليد رمز توثيق تسعيري.
     *
     * @param  array<int, array{package_id: int, voucher_count: int}>  $items
     * @return array<string, mixed>
     */
    public function quoteNewRequest(
        int $networkId,
        int $posId,
        SaleType $saleType,
        array $items
    ): array {
        $quoteItems = [];
        $grossTotal = 0.0;
        $netTotal = 0.0;

        foreach ($items as $item) {
            $packageId = (int) $item['package_id'];
            $voucherCount = (int) $item['voucher_count'];

            if ($voucherCount < 1) {
                throw ValidationException::withMessages([
                    'items' => ["الكمية المطلوبة للباقة [{$packageId}] يجب أن تكون كرت واحد على الأقل."],
                ]);
            }

            $package = $this->voucherPackageRepository->findById($packageId);
            if (! $package || (int) $package->network_id !== $networkId) {
                $pkgName = $package ? $package->package_name : (string) $packageId;
                throw ValidationException::withMessages([
                    'items' => ["الباقة ({$pkgName}) لا تنتمي لمزود الشبكة المحدد."],
                ]);
            }
            $package->ensureActive();

            $unitPrice = (float) $package->package_voucher_price;
            $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $packageId);
            $discountPercent = $saleType->isCash()
                ? (float) ($discountData['discount_cash'] ?? 0.0)
                : (float) ($discountData['discount_credit'] ?? 0.0);

            $lineGross = $voucherCount * $unitPrice;
            $lineNet = round($lineGross * (1.0 - ($discountPercent / 100.0)), 2);
            $lineDiscount = round($lineGross - $lineNet, 2);
            $effectiveUnitPrice = round($unitPrice * (1.0 - ($discountPercent / 100.0)), 2);

            $grossTotal += $lineGross;
            $netTotal += $lineNet;

            $itemToken = $this->generatePricingToken(
                0,
                $networkId,
                $posId,
                $packageId,
                $saleType,
                $unitPrice,
                $discountPercent
            );

            $quoteItems[] = [
                'package_id' => $packageId,
                'package_name' => $package->package_name,
                'package_color' => $package->package_color,
                'package_voucher_count' => (int) $package->package_voucher_count,
                'voucher_count' => $voucherCount,
                'unit_price' => $unitPrice,
                'discount_rate' => $discountPercent,
                'effective_price' => $effectiveUnitPrice,
                'line_gross_total' => round($lineGross, 2),
                'line_discount_amount' => $lineDiscount,
                'line_net_total' => $lineNet,
                'is_custom_discount' => (bool) ($discountData['is_custom'] ?? false),
                'pricing_token' => $itemToken,
            ];
        }

        $grossTotal = round($grossTotal, 2);
        $netTotal = round($netTotal, 2);
        $discountTotal = round($grossTotal - $netTotal, 2);

        $orderToken = $this->generateOrderPricingToken(
            $networkId,
            $posId,
            $saleType,
            $netTotal,
            $quoteItems
        );

        return [
            'network_id' => $networkId,
            'pos_id' => $posId,
            'sale_type' => $saleType->value,
            'sale_type_label' => $saleType->label(),
            'gross_total' => $grossTotal,
            'discount_total' => $discountTotal,
            'net_total' => $netTotal,
            'currency' => 'YER',
            'pricing_token' => $orderToken,
            'expires_at' => now('Asia/Aden')->addMinutes(30)->toIso8601String(),
            'items' => $quoteItems,
        ];
    }

    /**
     * توليد رمز تسعيري شامل للطلب كاملاً موقّع ببصمة HMAC.
     */
    public function generateOrderPricingToken(
        int $networkId,
        int $posId,
        SaleType $saleType,
        float $netTotal,
        array $items
    ): string {
        $itemsSnapshot = array_map(fn ($it) => [
            'pid' => (int) $it['package_id'],
            'qty' => (int) $it['voucher_count'],
            'pr' => round((float) $it['unit_price'], 2),
            'dc' => round((float) $it['discount_rate'], 2),
        ], $items);

        $payload = [
            'v' => 1,
            'nid' => $networkId,
            'pos' => $posId,
            'st' => $saleType->value,
            'tot' => round($netTotal, 2),
            'it' => $itemsSnapshot,
            'ts' => now('Asia/Aden')->timestamp,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', (string) $json, (string) config('app.key'));
        $packet = json_encode(['d' => $payload, 's' => $sig], JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode((string) $packet), '+/', '-_'), '=');
    }

    /**
     * التحقق الصارم من صحة وتطابق رمز عرض الأسعار الشامل عند تقديم الطلب.
     * يرفض الطلب فوراً إذا انتهت صلاحيته أو تم التلاعب به أو تغيرت أسعار الكتالوج أو الخصومات.
     *
     * @param array<int, array{package_id: int, voucher_count: int}> $items
     */
    public function verifyOrderPricingToken(
        int $networkId,
        int $posId,
        SaleType $saleType,
        array $items,
        string $token,
        int $maxLifetimeSeconds = 1800 // 30 minutes
    ): void {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            throw ValidationException::withMessages([
                'pricing' => ['رمز عرض الأسعار غير صالح أو تالف. يرجى مراجعة وتحديث عرض الأسعار.'],
            ]);
        }

        $packet = json_decode($decoded, true);
        if (! is_array($packet) || ! isset($packet['d'], $packet['s'])) {
            throw ValidationException::withMessages([
                'pricing' => ['بنية رمز عرض الأسعار غير متوافقة. يرجى طلب معاينة جديدة.'],
            ]);
        }

        $payload = $packet['d'];
        $sig = $packet['s'];

        $expectedSig = hash_hmac('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES), (string) config('app.key'));
        if (! hash_equals($expectedSig, $sig)) {
            throw ValidationException::withMessages([
                'pricing' => ['تم اكتشاف تلاعب أو عدم تطابق في توقيع رمز عرض الأسعار. يرجى إعادة المعاينة.'],
            ]);
        }

        if (
            (int) ($payload['nid'] ?? 0) !== $networkId ||
            (int) ($payload['pos'] ?? 0) !== $posId ||
            (string) ($payload['st'] ?? '') !== $saleType->value
        ) {
            throw ValidationException::withMessages([
                'pricing' => ['رمز عرض الأسعار لا يطابق سياق الشبكة أو نقطة البيع أو طريقة الدفع الحالية.'],
            ]);
        }

        $timestamp = (int) ($payload['ts'] ?? 0);
        $now = now('Asia/Aden')->timestamp;
        if (($now - $timestamp) > $maxLifetimeSeconds) {
            throw ValidationException::withMessages([
                'pricing' => ['انتهت صلاحية عرض الأسعار (أكثر من 30 دقيقة). يرجى تحديث المعاينة قبل الإرسال.'],
            ]);
        }

        // التحقق الحاسم من ثبات أسعار الكتالوج والخصومات الفعلية الحالية مع ما تم اعتماده في الرمز
        $tokenItems = $payload['it'] ?? [];
        $tokenItemsMap = [];
        foreach ($tokenItems as $ti) {
            $tokenItemsMap[(int) $ti['pid']] = $ti;
        }

        foreach ($items as $item) {
            $packageId = (int) $item['package_id'];
            if (! isset($tokenItemsMap[$packageId])) {
                throw ValidationException::withMessages([
                    'pricing' => ['تغيرت بنود الطلب عن عرض الأسعار المعتمد. يرجى طلب معاينة جديدة.'],
                ]);
            }

            $ti = $tokenItemsMap[$packageId];
            $package = $this->voucherPackageRepository->findById($packageId);
            $currentPrice = (float) $package->package_voucher_price;
            $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $packageId);
            $currentDiscount = $saleType->isCash()
                ? (float) ($discountData['discount_cash'] ?? 0.0)
                : (float) ($discountData['discount_credit'] ?? 0.0);

            if (round($currentPrice, 2) !== round((float) $ti['pr'], 2) || round($currentDiscount, 2) !== round((float) $ti['dc'], 2)) {
                throw ValidationException::withMessages([
                    'pricing' => ['تم تحديث أسعار الباقات أو شروط الخصم المعتمدة لدى الشبكة. يرجى مراجعة وتحديث عرض الأسعار قبل الإرسال.'],
                ]);
            }
        }
    }
}
