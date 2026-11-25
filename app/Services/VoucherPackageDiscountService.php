<?php

namespace App\Services;

use App\Http\Requests\API\V1\Network\StoreVoucherPackageDiscountRequest;
use App\Http\Requests\API\V1\Network\UpdateVoucherPackageDiscountRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\VoucherPackageDiscountRepositoryInterface;
use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
use App\Models\NetworkPosEvent;
use App\Services\Billing\Money;
use App\Support\DecimalAmount;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة خصومات وعمولات باقات الكروت لنقاط البيع (Voucher Package Discount Service).
 *
 * تتولى العمليات الحسابية والمنطقية لتخصيص الخصومات:
 * 1. استرجاع الخصومات المخصصة والفلترة والترقيم بالمؤشر (Cursor Pagination).
 * 2. ربط نقطة بيع بخصم استثنائي لباقة محددة مع التحقق من انتماء الباقة للشبكة.
 * 3. احتساب الخصم الفعلي المطبق (Effective Discount) عبر إرجاع الخصم المخصص أو الخصم الافتراضي للباقة.
 * 4. الحذف المؤقت (Soft Delete)، الاستعادة، والحذف النهائي والعمليات المجمعة.
 */
class VoucherPackageDiscountService
{
    public function __construct(
        protected VoucherPackageDiscountRepositoryInterface $discountRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected VoucherPackageRepositoryInterface $packageRepository,
        protected ?NetworkPosRepositoryInterface $networkPosRepository = null,
        protected ?ProcurementPricingSnapshotService $pricingSnapshotService = null,
    ) {
        $this->networkPosRepository = $networkPosRepository ?? app(NetworkPosRepositoryInterface::class);
        $this->pricingSnapshotService = $pricingSnapshotService ?? app(ProcurementPricingSnapshotService::class);
    }

    /**
     * جلب قائمة الخصومات مع الفلترة والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos', 'voucherPackage']): Collection|CursorPaginator
    {
        return $this->discountRepository->getFiltered($filters, $relations);
    }

    /**
     * البحث عن سجل خصم محدد عبر المعرف.
     */
    public function findById(int $id, array $relations = ['network', 'pos', 'voucherPackage']): ?VoucherPackageDiscount
    {
        try {
            $discount = $this->discountRepository->findById($id);

            return ! empty($relations) ? $discount->load($relations) : $discount;
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * إنشاء خصم مخصص جديد لنقطة بيع على باقة معينة.
     */
    public function create(StoreVoucherPackageDiscountRequest|array $request, int $createdBy): VoucherPackageDiscount
    {
        if ($request instanceof StoreVoucherPackageDiscountRequest) {
            $networkId = $request->getNetworkId();
            $posId = $request->getPosId();
            $packageId = $request->getPackageId();
            $discountCash = $request->getDiscountCash();
            $discountCredit = $request->getDiscountCredit();
            $status = $request->getStatus();
            $discountStartDate = $request->getDiscountStartDate();
            $discountEndDate = $request->getDiscountEndDate();
        } else {
            $networkId = (int) $request['network_id'];
            $posId = (int) $request['pos_id'];
            $packageId = (int) $request['package_id'];
            $discountCash = (float) $request['discount_cash'];
            $discountCredit = (float) $request['discount_credit'];
            $status = isset($request['status']) ? (bool) $request['status'] : true;
            $discountStartDate = array_key_exists('discount_start_date', $request)
                ? StoreVoucherPackageDiscountRequest::parseDateBoundary($request['discount_start_date'], false)
                : now('Asia/Aden');
            $discountEndDate = array_key_exists('discount_end_date', $request)
                ? StoreVoucherPackageDiscountRequest::parseDateBoundary($request['discount_end_date'], true)
                : null;
        }

        return DB::transaction(function () use ($networkId, $posId, $packageId, $discountCash, $discountCredit, $status, $discountStartDate, $discountEndDate, $createdBy) {
            // 1. التحقق من وجود ونشاط الشبكة
            try {
                $network = $this->networkRepository->findById($networkId);
                $network->ensureActive();
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'network_id' => ['مزود الشبكة المحدد غير موجود في النظام.'],
                ]);
            }

            // 2. التحقق من وجود ونشاط نقطة البيع
            try {
                $pos = $this->posRepository->findById($posId);
                $pos->ensureActive();
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير موجودة في النظام.'],
                ]);
            }

            // 3. التحقق من ارتباط نقطة البيع بالشبكة وتفعيله
            $link = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
            if (! $link) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير مرتبطة بمزود الشبكة هذا.'],
                ]);
            }
            $link->ensureActive();

            // 4. التحقق من وجود ونشاط الباقة وانتمائها لنفس الشبكة
            try {
                $package = $this->packageRepository->findById($packageId);
                $package->ensureActive();
                if ((int) $package->network_id !== $networkId) {
                    throw ValidationException::withMessages([
                        'package_id' => ['باقة الكروت المحددة لا تنتمي لمزود الشبكة المختار.'],
                    ]);
                }
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'package_id' => ['باقة الكروت المحددة غير موجودة في النظام.'],
                ]);
            }

            // منع تكرار الخصم لنفس (الشبكة + نقطة البيع + الباقة)
            $existing = $this->discountRepository->findByNetworkPosAndPackage($networkId, $posId, $packageId);
            if ($existing) {
                throw ValidationException::withMessages([
                    'package_id' => ['يوجد خصم مسجل مسبقاً لنقطة البيع هذه على نفس الباقة.'],
                ]);
            }

            try {
                $discount = $this->discountRepository->create([
                    'network_id' => $networkId,
                    'pos_id' => $posId,
                    'package_id' => $packageId,
                    'created_by' => $createdBy,
                    'discount_cash' => $discountCash,
                    'discount_credit' => $discountCredit,
                    'status' => $status,
                    'discount_start_date' => $discountStartDate,
                    'discount_end_date' => $discountEndDate,
                ]);

                $this->recordRelationshipEvent($discount, 'DISCOUNT_CREATED');

                return $discount->load(['network', 'pos', 'voucherPackage']);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'package_id' => ['يوجد خصم مسجل مسبقاً لنقطة البيع هذه على نفس الباقة.'],
                ]);
            }
        });
    }

    /**
     * تحديث بيانات خصم قائم.
     */
    public function update(int $id, UpdateVoucherPackageDiscountRequest|array $request): VoucherPackageDiscount
    {
        try {
            $discount = $this->discountRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'discount_id' => ['سجل الخصم المحدد غير موجود في النظام.'],
            ]);
        }

        if ($request instanceof UpdateVoucherPackageDiscountRequest) {
            $data = [];
            if ($request->has('discount_cash')) {
                $data['discount_cash'] = (float) $request->discount_cash;
            }
            if ($request->has('discount_credit')) {
                $data['discount_credit'] = (float) $request->discount_credit;
            }
            if ($request->has('status')) {
                $data['status'] = (bool) $request->status;
            }
            if ($request->has('discount_start_date')) {
                $data['discount_start_date'] = StoreVoucherPackageDiscountRequest::parseDateBoundary(
                    $request->input('discount_start_date'),
                    false
                );
            }
            if ($request->has('discount_end_date')) {
                $data['discount_end_date'] = StoreVoucherPackageDiscountRequest::parseDateBoundary(
                    $request->input('discount_end_date'),
                    true
                );
            }
        } else {
            $data = $request;
            if (array_key_exists('discount_start_date', $data)) {
                $data['discount_start_date'] = StoreVoucherPackageDiscountRequest::parseDateBoundary(
                    $data['discount_start_date'],
                    false
                );
            }
            if (array_key_exists('discount_end_date', $data)) {
                $data['discount_end_date'] = StoreVoucherPackageDiscountRequest::parseDateBoundary(
                    $data['discount_end_date'],
                    true
                );
            }
        }

        return DB::transaction(function () use ($discount, $data) {
            $this->discountRepository->update($discount->discount_id, $data);
            $discount->refresh();
            $this->recordRelationshipEvent($discount, 'DISCOUNT_UPDATED', ['changed_fields' => array_keys($data)]);

            return $discount->load(['network', 'pos', 'voucherPackage']);
        });
    }

    /**
     * الحذف المؤقت للخصم (Soft Delete).
     */
    public function delete(int $id): bool
    {
        try {
            $discount = $this->discountRepository->findById($id);
        } catch (ModelNotFoundException) {
            return false;
        }

        return DB::transaction(function () use ($discount) {
            $deleted = (bool) $this->discountRepository->delete($discount->discount_id);
            if ($deleted) {
                $this->recordRelationshipEvent($discount, 'DISCOUNT_ARCHIVED');
            }
            return $deleted;
        });
    }

    /**
     * استرجاع قائمة الخصومات المحذوفة مؤقتاً.
     */
    public function getTrashed(int $perPage = 15, array $filters = []): CursorPaginator
    {
        return $this->discountRepository->getTrashed($perPage, $filters);
    }

    /**
     * استعادة خصم محذوف مؤقتاً.
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $discount = $this->discountRepository->findTrashedById($id);
            if (! $discount) {
                return false;
            }

            $existing = $this->discountRepository->findByNetworkPosAndPackage(
                $discount->network_id,
                $discount->pos_id,
                $discount->package_id
            );
            if ($existing) {
                throw ValidationException::withMessages([
                    'discount' => ['لا يمكن استعادة هذا الخصم لوجود خصم نشط مسجل مسبقاً لنفس نقطة البيع والباقة.'],
                ]);
            }

            try {
                $restored = $this->discountRepository->restore($id);
                if ($restored) {
                    $this->recordRelationshipEvent($discount, 'DISCOUNT_RESTORED');
                }
                return $restored;
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'discount' => ['لا يمكن استعادة هذا الخصم لوجود خصم نشط مسجل مسبقاً لنفس نقطة البيع والباقة.'],
                ]);
            }
        });
    }

    /**
     * الحذف النهائي للخصم من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->discountRepository->forceDelete($id);
        });
    }

    private function recordRelationshipEvent(
        VoucherPackageDiscount $discount,
        string $eventType,
        array $payload = [],
    ): void {
        $link = $this->networkPosRepository->findByNetworkAndPos(
            (int) $discount->network_id,
            (int) $discount->pos_id,
            true,
        );
        if (! $link) {
            return;
        }

        NetworkPosEvent::query()->create([
            'network_pos_id' => $link->network_pos_id,
            'actor_user_id' => auth()->user()?->user_id,
            'event_type' => $eventType,
            'payload' => array_merge([
                'discount_id' => $discount->discount_id,
                'package_id' => $discount->package_id,
            ], $payload),
        ]);
    }

    /**
     * حذف مجمع لعدة خصومات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->discountRepository->bulkDelete($ids);
        });
    }

    /**
     * استعادة مجمعة لعدة خصومات من سلة المحذوفات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $trashed = VoucherPackageDiscount::onlyTrashed()->whereIn('discount_id', $ids)->get();

            // فحص التكرار الداخلي بين السجلات المراد استعادتها ضمن نفس الدفعة
            $grouped = $trashed->groupBy(fn ($d) => "{$d->network_id}_{$d->pos_id}_{$d->package_id}");
            foreach ($grouped as $group) {
                if ($group->count() > 1) {
                    throw ValidationException::withMessages([
                        'ids' => ['تحتوي القائمة المحددة على أكثر من سجل محذوف لنفس توليفة الشبكة ونقطة البيع والباقة.'],
                    ]);
                }
            }

            // فحص التعارض المسبق مع السجلات النشطة الحالية
            foreach ($trashed as $discount) {
                $existing = $this->discountRepository->findByNetworkPosAndPackage(
                    $discount->network_id,
                    $discount->pos_id,
                    $discount->package_id
                );
                if ($existing) {
                    throw ValidationException::withMessages([
                        'ids' => ["لا يمكن استعادة الخصم رقم [{$discount->discount_id}] لوجود خصم نشط مسجل مسبقاً لنفس نقطة البيع والباقة."],
                    ]);
                }
            }

            try {
                return $this->discountRepository->bulkRestore($ids);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'ids' => ['تعذر الاستعادة المجمعة لوجود تعارض فريد مع خصم نشط مسجل مسبقاً لنفس نقطة البيع والباقة.'],
                ]);
            }
        });
    }

    /**
     * حذف نهائي مجمع لعدة خصومات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->discountRepository->bulkForceDelete($ids);
        });
    }

    /**
     * تبديل حالة تفعيل الخصم.
     */
    public function toggleStatus(int $id): VoucherPackageDiscount
    {
        try {
            $discount = $this->discountRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'discount_id' => ['سجل الخصم المحدد غير موجود.'],
            ]);
        }

        return DB::transaction(function () use ($discount) {
            $newStatus = ! (bool) $discount->status;
            $this->discountRepository->update($discount->discount_id, [
                'status' => $newStatus,
            ]);

            $discount->refresh();
            $this->recordRelationshipEvent($discount, $newStatus ? 'DISCOUNT_ACTIVATED' : 'DISCOUNT_DEACTIVATED');

            return $discount->load(['network', 'pos', 'voucherPackage']);
        });
    }

    /**
     * احتساب الخصم الفعلي لنقطة بيع محددة على باقة معينة.
     * يعيد الخصم المخصص إن وُجد وكان مفعّلاً، وإلا يعيد الخصم الافتراضي للباقة.
     *
     * @return array{discount_cash: float, discount_credit: float, is_custom: bool}
     */
    public function getEffectiveDiscount(int $networkId, int $posId, int $packageId): array
    {
        $custom = $this->discountRepository->findEffectiveDiscount($networkId, $posId, $packageId);
        $package = $this->packageRepository->findById($packageId);
        $originalPrice = (float) $package->package_voucher_price;

        if ($custom) {
            $cashRate = (float) $custom->discount_cash;
            $creditRate = (float) $custom->discount_credit;

            return [
                'discount_cash' => $cashRate,
                'discount_credit' => $creditRate,
                'is_custom' => true,
                'original_package_price' => round($originalPrice, 2),
                'effective_cash_price' => round($originalPrice - (($originalPrice * $cashRate) / 100), 2),
                'effective_credit_price' => round($originalPrice - (($originalPrice * $creditRate) / 100), 2),
                'cash_savings' => round(($originalPrice * $cashRate) / 100, 2),
                'credit_savings' => round(($originalPrice * $creditRate) / 100, 2),
            ];
        }

        $cashRate = (float) $package->package_default_cash_discount;
        $creditRate = (float) $package->package_default_credit_discount;

        return [
            'discount_cash' => $cashRate,
            'discount_credit' => $creditRate,
            'is_custom' => false,
            'original_package_price' => round($originalPrice, 2),
            'effective_cash_price' => round($originalPrice - (($originalPrice * $cashRate) / 100), 2),
            'effective_credit_price' => round($originalPrice - (($originalPrice * $creditRate) / 100), 2),
            'cash_savings' => round(($originalPrice * $cashRate) / 100, 2),
            'credit_savings' => round(($originalPrice * $creditRate) / 100, 2),
        ];
    }

    /**
     * إرفاق لقطة تعرفة دفعيّة بباقات صفحة الكتالوج، خاصة بنقطة البيع المحددة.
     * القيم الصحيحة بوحدة العملة الصغرى ومعدلات الخصم بوحدات basis points.
     *
     * @param  Collection<int, VoucherPackage>|CursorPaginator  $packages
     */
    public function attachProcurementPricing(int $networkId, int $posId, Collection|CursorPaginator $packages): void
    {
        $packageCollection = $packages instanceof Collection
            ? $packages
            : collect($packages->items());

        if ($packageCollection->isEmpty()) {
            return;
        }

        $packageIds = $packageCollection->pluck('package_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $discountsByPackage = $this->discountRepository
            ->findEffectiveDiscountsForPackages($networkId, $posId, $packageIds)
            ->keyBy('package_id');

        foreach ($packageCollection as $package) {
            $customDiscount = $discountsByPackage->get($package->package_id);
            $basePriceMinor = DecimalAmount::toMinorUnits($package->getAttribute('package_voucher_price'));
            $cashRateBasisPoints = DecimalAmount::toMinorUnits(
                $customDiscount?->discount_cash ?? $package->package_default_cash_discount
            );
            $creditRateBasisPoints = DecimalAmount::toMinorUnits(
                $customDiscount?->discount_credit ?? $package->package_default_credit_discount
            );
            $baseMoney = new Money($basePriceMinor, 'YER');
            $cashDiscountMinor = $baseMoney->applyBasisPoints($cashRateBasisPoints)->amountMinor;
            $creditDiscountMinor = $baseMoney->applyBasisPoints($creditRateBasisPoints)->amountMinor;
            $issuedAt = now('Asia/Aden')->startOfSecond();
            $pricingSnapshotToken = $this->pricingSnapshotService->issue(
                $networkId,
                $posId,
                (int) $package->package_id,
                $basePriceMinor,
                $cashRateBasisPoints,
                $creditRateBasisPoints,
                $issuedAt->timestamp,
            );

            $package->setRelation('procurementPricing', [
                'base_unit_price_minor' => $basePriceMinor,
                'cash_discount_basis_points' => $cashRateBasisPoints,
                'credit_discount_basis_points' => $creditRateBasisPoints,
                'cash_effective_unit_price_minor' => $basePriceMinor - $cashDiscountMinor,
                'credit_effective_unit_price_minor' => $basePriceMinor - $creditDiscountMinor,
                'is_custom' => $customDiscount !== null,
                'pricing_snapshot_token' => $pricingSnapshotToken,
                'pricing_snapshot_expires_at' => $issuedAt->copy()
                    ->addSeconds(ProcurementPricingSnapshotService::MAX_AGE_SECONDS)
                    ->toDateTimeString(),
            ]);
        }
    }
}
