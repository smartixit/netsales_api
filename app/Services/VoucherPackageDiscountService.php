<?php

namespace App\Services;

use App\Http\Requests\API\V1\Network\StoreVoucherPackageDiscountRequest;
use App\Http\Requests\API\V1\Network\UpdateVoucherPackageDiscountRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\VoucherPackageDiscountRepositoryInterface;
use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Models\VoucherPackageDiscount;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    ) {
        $this->networkPosRepository = $networkPosRepository ?? app(NetworkPosRepositoryInterface::class);
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
        } else {
            $networkId = (int) $request['network_id'];
            $posId = (int) $request['pos_id'];
            $packageId = (int) $request['package_id'];
            $discountCash = (float) $request['discount_cash'];
            $discountCredit = (float) $request['discount_credit'];
            $status = isset($request['status']) ? (bool) $request['status'] : true;
        }

        return DB::transaction(function () use ($networkId, $posId, $packageId, $discountCash, $discountCredit, $status, $createdBy) {
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

            $discount = $this->discountRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'package_id' => $packageId,
                'created_by' => $createdBy,
                'discount_cash' => $discountCash,
                'discount_credit' => $discountCredit,
                'status' => $status,
            ]);

            return $discount->load(['network', 'pos', 'voucherPackage']);
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
            $data = array_filter([
                'discount_cash' => $request->discount_cash !== null ? (float) $request->discount_cash : null,
                'discount_credit' => $request->discount_credit !== null ? (float) $request->discount_credit : null,
                'status' => $request->status !== null ? (bool) $request->status : null,
            ], fn ($val) => $val !== null);
        } else {
            $data = $request;
        }

        return DB::transaction(function () use ($discount, $data) {
            $this->discountRepository->update($discount->discount_id, $data);
            $discount->refresh();

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
            return (bool) $this->discountRepository->delete($discount->discount_id);
        });
    }

    /**
     * استرجاع قائمة الخصومات المحذوفة مؤقتاً.
     */
    public function getTrashed(int $perPage = 15): CursorPaginator
    {
        return $this->discountRepository->getTrashed($perPage);
    }

    /**
     * استعادة خصم محذوف مؤقتاً.
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->discountRepository->restore($id);
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
            return $this->discountRepository->bulkRestore($ids);
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
        $custom = $this->discountRepository->findByNetworkPosAndPackage($networkId, $posId, $packageId);
        $package = $this->packageRepository->findById($packageId);
        $originalPrice = (float) $package->package_voucher_price;

        if ($custom && (bool) $custom->status) {
            $cashRate = (float) $custom->discount_cash;
            $creditRate = (float) $custom->discount_credit;

            return [
                'discount_cash' => $cashRate,
                'discount_credit' => $creditRate,
                'is_custom' => true,
                // Server-authoritative derived monetary values (Frontend Display-Only, AGENTS.md §34)
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
            // Server-authoritative derived monetary values (Frontend Display-Only, AGENTS.md §34)
            'original_package_price' => round($originalPrice, 2),
            'effective_cash_price' => round($originalPrice - (($originalPrice * $cashRate) / 100), 2),
            'effective_credit_price' => round($originalPrice - (($originalPrice * $creditRate) / 100), 2),
            'cash_savings' => round(($originalPrice * $cashRate) / 100, 2),
            'credit_savings' => round(($originalPrice * $creditRate) / 100, 2),
        ];
    }
}
