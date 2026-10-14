<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Http\Requests\API\V1\Request\StoreRequestRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\RequestItemRepositoryInterface;
use App\Interfaces\RequestRepositoryInterface;
use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Models\Request as RequestModel;
use App\Models\SaleInvoice;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة دورة حياة طلبات الكروت واعتمادها (Enterprise Voucher Request Service).
 */
class RequestService
{
    public function __construct(
        protected RequestRepositoryInterface $requestRepository,
        protected RequestItemRepositoryInterface $requestItemRepository,
        protected VoucherPackageRepositoryInterface $voucherPackageRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected NetworkPosRepositoryInterface $networkPosRepository,
        protected SaleInvoiceService $saleInvoiceService
    ) {}

    /**
     * جلب قائمة الطلبات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->requestRepository->getFiltered($filters, ['network', 'pos', 'createdBy', 'reviewedBy', 'items.voucherPackage']);
    }

    /**
     * جلب الطلبات المحذوفة مؤقتاً.
     */
    public function getTrashed(array $filters = []): Collection|CursorPaginator
    {
        return $this->requestRepository->getTrashedFiltered($filters, ['network', 'pos']);
    }

    /**
     * عرض تفاصيل طلب محدد مع بنوده والجهات المرتبطة.
     */
    public function getById(int $requestId): RequestModel
    {
        /** @var RequestModel $request */
        $request = $this->requestRepository->findById($requestId);
        $request->loadMissing(['network', 'pos', 'createdBy', 'reviewedBy', 'items.voucherPackage']);

        return $request;
    }

    /**
     * إنشاء طلب كروت جديد من نقطة البيع لمزود الشبكة.
     */
    public function create(StoreRequestRequest $request, int $userId): RequestModel
    {
        return DB::transaction(function () use ($request, $userId): RequestModel {
            $networkId = $request->getNetworkId();
            $posId = $request->getPosId();
            $items = $request->getItems();
            $note = $request->getRequestNote();

            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => ['يجب إضافة باقة واحدة على الأقل في الطلب.'],
                ]);
            }

            // التحقق من وجود ونشاط الشبكة ونقطة البيع
            $network = $this->networkRepository->findById($networkId);
            $pos = $this->posRepository->findById($posId);

            // التحقق من ارتباط نقطة البيع بالشبكة وتفعيله
            $link = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
            if (! $link || ! $link->status) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع غير مرتبطة بهذه الشبكة أو أن الارتباط معطل حالياً.'],
                ]);
            }

            // التحقق من صحة الباقات وتبعيتها للشبكة واحتساب الإجمالي المبدئي
            $totalAmount = 0.0;
            $itemsData = [];

            foreach ($items as $item) {
                $packageId = (int) $item['package_id'];
                $count = (int) $item['item_voucher_count'];

                $package = $this->voucherPackageRepository->findById($packageId);

                if ((int) $package->network_id !== $networkId) {
                    throw ValidationException::withMessages([
                        'items' => ["الباقة ({$package->package_name}) لا تنتمي لمزود الشبكة المحدد."],
                    ]);
                }

                if (! $package->status) {
                    throw ValidationException::withMessages([
                        'items' => ["الباقة ({$package->package_name}) معطلة حالياً ولا يمكن طلبها."],
                    ]);
                }

                $totalAmount += round((float) $package->package_voucher_price * $count, 2);

                $itemsData[] = [
                    'package_id' => $packageId,
                    'item_voucher_count' => $count,
                ];
            }

            /** @var RequestModel $createdRequest */
            $createdRequest = $this->requestRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'request_total_amount' => $totalAmount,
                'request_date' => now('Asia/Aden'),
                'status' => RequestStatus::PENDING,
                'created_by' => $userId,
                'request_note' => $note,
            ]);

            $this->requestItemRepository->createManyForRequest($createdRequest->request_id, $itemsData);

            $createdRequest->load(['network', 'pos', 'createdBy', 'items.voucherPackage']);

            return $createdRequest;
        });
    }

    /**
     * اعتماد طلب الكروت وتوليد فاتورة المبيعات وتسوية الرصيد آلياً.
     *
     * @return array{request: RequestModel, invoice: SaleInvoice}
     */
    public function approve(int $requestId, int $reviewedBy, ?string $note = null): array
    {
        return DB::transaction(function () use ($requestId, $reviewedBy, $note): array {
            /** @var RequestModel $request */
            $request = $this->requestRepository->findById($requestId);

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن اعتماد هذا الطلب لأنه ليس في حالة قيد الانتظار (PENDING).'],
                ]);
            }

            // توليد الفاتورة والخصم من محفظة الوكيل والإيداع في محفظة الشبكة ذرياً
            $invoice = $this->saleInvoiceService->generateFromRequest($request, $reviewedBy);

            // تحديث حالة الطلب إلى معتمد (APPROVED)
            $this->requestRepository->updateStatus(
                $requestId,
                RequestStatus::APPROVED,
                $reviewedBy,
                $note ?? $request->request_note
            );

            $request->refresh()->load(['network', 'pos', 'createdBy', 'reviewedBy', 'items.voucherPackage']);

            return [
                'request' => $request,
                'invoice' => $invoice,
            ];
        });
    }

    /**
     * رفض طلب الكروت مع توثيق السبب.
     */
    public function reject(int $requestId, int $reviewedBy, string $reason): RequestModel
    {
        return DB::transaction(function () use ($requestId, $reviewedBy, $reason): RequestModel {
            /** @var RequestModel $request */
            $request = $this->requestRepository->findById($requestId);

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن رفض هذا الطلب لأنه ليس في حالة قيد الانتظار (PENDING).'],
                ]);
            }

            $currentNote = (string) $request->request_note;
            $updatedNote = trim($currentNote." | سبب الرفض: {$reason}", ' |');

            $this->requestRepository->updateStatus(
                $requestId,
                RequestStatus::REJECTED,
                $reviewedBy,
                $updatedNote
            );

            return $this->getById($requestId);
        });
    }

    /**
     * إلغاء الطلب من قبل صاحب الطلب قبل المراجعة.
     */
    public function cancel(int $requestId, int $userId): RequestModel
    {
        return DB::transaction(function () use ($requestId, $userId): RequestModel {
            /** @var RequestModel $request */
            $request = $this->requestRepository->findById($requestId);

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن إلغاء هذا الطلب لأنه تم البت فيه مسبقاً.'],
                ]);
            }

            if ((int) $request->created_by !== $userId) {
                throw ValidationException::withMessages([
                    'user' => ['غير مصرح لك بإلغاء هذا الطلب.'],
                ]);
            }

            $this->requestRepository->updateStatus(
                $requestId,
                RequestStatus::CANCELLED,
                $userId,
                'تم إلغاء الطلب بواسطة المستخدم'
            );

            return $this->getById($requestId);
        });
    }

    /**
     * حذف طلب مؤقتاً.
     */
    public function deleteRequest(int $requestId): bool
    {
        return DB::transaction(function () use ($requestId): bool {
            return (bool) $this->requestRepository->delete($requestId);
        });
    }

    /**
     * استعادة طلب محذوف.
     */
    public function restoreRequest(int $requestId): bool
    {
        return DB::transaction(function () use ($requestId): bool {
            return (bool) $this->requestRepository->restore($requestId);
        });
    }

    /**
     * الحذف النهائي لطلب.
     */
    public function forceDeleteRequest(int $requestId): bool
    {
        return DB::transaction(function () use ($requestId): bool {
            $this->requestItemRepository->deleteByRequestId($requestId);

            return (bool) $this->requestRepository->forceDelete($requestId);
        });
    }

    /**
     * الحذف المجمع للطلبات.
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->requestRepository->bulkDelete($ids);
        });
    }

    /**
     * الاستعادة المجمعة للطلبات.
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->requestRepository->bulkRestore($ids);
        });
    }

    /**
     * الحذف النهائي المجمع للطلبات.
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            return $this->requestRepository->bulkForceDelete($ids);
        });
    }
}
