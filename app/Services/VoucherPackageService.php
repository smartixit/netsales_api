<?php

namespace App\Services;

use App\Http\Requests\API\V1\Network\StoreVoucherPackageRequest;
use App\Http\Requests\API\V1\Network\UpdateVoucherPackageRequest;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Models\VoucherPackage;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة باقات وكروت الإنترنت (Enterprise Voucher Package Service).
 *
 * تتولى كافة العمليات المنطقية لباقات الكروت:
 * 1. استرجاع الباقات مع الفلترة والبحث والترقيم بالمؤشر (Cursor Pagination).
 * 2. إنشاء وتحديث الباقات مع التحقق من تفرد الاسم داخل الشبكة.
 * 3. الحذف المؤقت (Soft Delete)، الاستعادة، والحذف النهائي والعمليات المجمعة.
 * 4. إدارة حالة التفعيل والتعطيل للباقات.
 */
class VoucherPackageService
{
    public function __construct(
        protected VoucherPackageRepositoryInterface $voucherPackageRepository,
        protected NetworkRepositoryInterface $networkRepository,
    ) {}

    /**
     * جلب قائمة الباقات مع الفلترة والبحث والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = ['network']): Collection|CursorPaginator
    {
        return $this->voucherPackageRepository->getFiltered($filters, $relations);
    }

    /**
     * البحث عن باقة محددة عبر المعرف.
     */
    public function findById(int $id, array $relations = ['network', 'discounts']): ?VoucherPackage
    {
        try {
            $package = $this->voucherPackageRepository->findById($id);

            return ! empty($relations) ? $package->load($relations) : $package;
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * إنشاء باقة كروت إنترنت جديدة.
     */
    public function create(StoreVoucherPackageRequest|array $request, int $createdBy): VoucherPackage
    {
        if ($request instanceof StoreVoucherPackageRequest) {
            $networkId = $request->getNetworkId();
            $data = [
                'network_id' => $networkId,
                'created_by' => $createdBy,
                'package_name' => $request->getPackageName(),
                'package_color' => $request->getPackageColor(),
                'package_balance' => $request->getPackageBalance(),
                'package_uptime' => $request->getPackageUptime(),
                'package_validity' => $request->getPackageValidity(),
                'package_voucher_count' => $request->getPackageVoucherCount(),
                'package_voucher_price' => $request->getPackageVoucherPrice(),
                'package_default_cash_discount' => $request->getPackageDefaultCashDiscount(),
                'package_default_credit_discount' => $request->getPackageDefaultCreditDiscount(),
                'status' => $request->getStatus(),
            ];
        } else {
            $networkId = (int) $request['network_id'];
            $data = array_merge($request, ['created_by' => $createdBy]);
        }

        return DB::transaction(function () use ($networkId, $data) {
            // التحقق من وجود ونشاط الشبكة
            try {
                $network = $this->networkRepository->findById($networkId);
                $network->ensureActive();
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'network_id' => ['مزود الشبكة المحدد غير موجود في النظام.'],
                ]);
            }

            // منع تكرار اسم الباقة داخل الشبكة
            $existing = $this->voucherPackageRepository->findByNetworkAndName($networkId, $data['package_name']);
            if ($existing) {
                throw ValidationException::withMessages([
                    'package_name' => ['اسم الباقة مسجل مسبقاً لهذه الشبكة.'],
                ]);
            }

            $package = $this->voucherPackageRepository->create($data);

            return $package->load('network');
        });
    }

    /**
     * تحديث بيانات باقة قائمة.
     */
    public function update(int $id, UpdateVoucherPackageRequest|array $request): VoucherPackage
    {
        try {
            $package = $this->voucherPackageRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'package_id' => ['باقة الكروت المحددة غير موجودة في النظام.'],
            ]);
        }

        if ($request instanceof UpdateVoucherPackageRequest) {
            $data = array_filter([
                'package_name' => $request->package_name,
                'package_color' => $request->package_color,
                'package_balance' => $request->package_balance,
                'package_uptime' => $request->package_uptime,
                'package_validity' => $request->package_validity,
                'package_voucher_count' => $request->package_voucher_count !== null ? (int) $request->package_voucher_count : null,
                'package_voucher_price' => $request->package_voucher_price !== null ? (float) $request->package_voucher_price : null,
                'package_default_cash_discount' => $request->package_default_cash_discount !== null ? (float) $request->package_default_cash_discount : null,
                'package_default_credit_discount' => $request->package_default_credit_discount !== null ? (float) $request->package_default_credit_discount : null,
                'status' => $request->status !== null ? (bool) $request->status : null,
            ], fn ($val) => $val !== null);
        } else {
            $data = $request;
        }

        return DB::transaction(function () use ($package, $data) {
            if (! empty($data['package_name']) && $data['package_name'] !== $package->package_name) {
                $existing = $this->voucherPackageRepository->findByNetworkAndName($package->network_id, $data['package_name']);
                if ($existing && $existing->package_id !== $package->package_id) {
                    throw ValidationException::withMessages([
                        'package_name' => ['اسم الباقة الجديد مستخدم بالفعل في هذه الشبكة.'],
                    ]);
                }
            }

            $this->voucherPackageRepository->update($package->package_id, $data);
            $package->refresh();

            return $package->load('network');
        });
    }

    /**
     * الحذف المؤقت للباقة (Soft Delete).
     */
    public function delete(int $id): bool
    {
        try {
            $package = $this->voucherPackageRepository->findById($id);
        } catch (ModelNotFoundException) {
            return false;
        }

        return DB::transaction(function () use ($package) {
            return (bool) $this->voucherPackageRepository->delete($package->package_id);
        });
    }

    /**
     * استرجاع قائمة الباقات المحذوفة مؤقتاً.
     */
    public function getTrashed(int $perPage = 15): CursorPaginator
    {
        return $this->voucherPackageRepository->getTrashed($perPage);
    }

    /**
     * استعادة باقة محذوفة مؤقتاً.
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->voucherPackageRepository->restore($id);
        });
    }

    /**
     * الحذف النهائي للباقة من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->voucherPackageRepository->forceDelete($id);
        });
    }

    /**
     * حذف مجمع لعدة باقات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->voucherPackageRepository->bulkDelete($ids);
        });
    }

    /**
     * استعادة مجمعة لعدة باقات من سلة المحذوفات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->voucherPackageRepository->bulkRestore($ids);
        });
    }

    /**
     * حذف نهائي مجمع لعدة باقات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->voucherPackageRepository->bulkForceDelete($ids);
        });
    }

    /**
     * تبديل حالة تفعيل الباقة.
     */
    public function toggleStatus(int $id): VoucherPackage
    {
        try {
            $package = $this->voucherPackageRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'package_id' => ['باقة الكروت المحددة غير موجودة.'],
            ]);
        }

        return DB::transaction(function () use ($package) {
            $newStatus = ! (bool) $package->status;
            $this->voucherPackageRepository->update($package->package_id, [
                'status' => $newStatus,
            ]);

            $package->refresh();

            return $package->load('network');
        });
    }

    /**
     * جلب باقات شبكة معينة بعد التحقق من وجود ونشاط الشبكة.
     */
    public function getForNetwork(int $networkId, array $filters = []): Collection|CursorPaginator
    {
        try {
            $network = $this->networkRepository->findById($networkId);
            $network->ensureActive();
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_id' => ['مزود الشبكة المحدد غير موجود في النظام.'],
            ]);
        }

        return $this->voucherPackageRepository->getForNetwork($networkId, $filters);
    }
}
