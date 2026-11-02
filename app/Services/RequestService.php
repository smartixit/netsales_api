<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\SaleType;
use App\Http\Requests\API\V1\Request\ApproveRequestRequest;
use App\Http\Requests\API\V1\Request\StoreRequestRequest;
use App\Http\Requests\API\V1\Request\UpdateRequestRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\RequestItemRepositoryInterface;
use App\Interfaces\RequestRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Models\Request as RequestModel;
use App\Models\RequestItem;
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
        protected SaleInvoiceService $saleInvoiceService,
        protected NotificationService $notificationService,
        protected VoucherPackageDiscountService $voucherPackageDiscountService,
        protected ?UserRepositoryInterface $userRepository = null,
        protected ?RequestPricingReviewService $pricingReviewService = null
    ) {
        $this->userRepository = $userRepository ?? app(UserRepositoryInterface::class);
        $this->pricingReviewService = $pricingReviewService ?? app(RequestPricingReviewService::class);
    }

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
            $saleType = $request->getSaleType();
            $items = $request->getItems();
            $note = $request->getRequestNote();

            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => ['يجب إضافة باقة واحدة على الأقل في الطلب.'],
                ]);
            }

            // التحقق من وجود ونشاط الشبكة ونقطة البيع
            $network = $this->networkRepository->findById($networkId);
            $network->ensureActive();

            $pos = $this->posRepository->findById($posId);
            $pos->ensureActive();

            // التحقق من صلاحية المستخدم على نقطة البيع
            $user = $this->userRepository->findById($userId);
            if ($user && ! $user->isSuperAdmin()) {
                $isAuthorized = ($user->pos && (int) $user->pos->pos_id === (int) $posId)
                    || (method_exists($user, 'posUsers') && $user->posUsers()->where('pos_id', $posId)->exists());

                if (! $isAuthorized) {
                    throw ValidationException::withMessages([
                        'pos_id' => ['غير مصرح لك بإنشاء طلب باسم نقطة البيع المحددة.'],
                    ]);
                }
            }

            // التحقق من ارتباط نقطة البيع بالشبكة وتفعيله
            $link = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
            if (! $link) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع غير مرتبطة بهذه الشبكة.'],
                ]);
            }
            $link->ensureActive();

            // التحقق من صحة الباقات وتبعيتها للشبكة واحتساب الإجمالي مع الخصومات المطبقة
            $totalAmount = 0.0;
            $itemsData = [];

            foreach ($items as $item) {
                $packageId = (int) $item['package_id'];
                $count = (int) $item['voucher_count'];

                $package = $this->voucherPackageRepository->findById($packageId);

                if ((int) $package->network_id !== $networkId) {
                    throw ValidationException::withMessages([
                        'items' => ["الباقة ({$package->package_name}) لا تنتمي لمزود الشبكة المحدد."],
                    ]);
                }

                $package->ensureActive();

                // SECURITY: use catalog price & server effective discount (ignore client inputs)
                $voucherPrice = (float) $package->package_voucher_price;
                $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $packageId);
                $cashDiscount = (float) ($discountData['discount_cash'] ?? 0.0);
                $creditDiscount = (float) ($discountData['discount_credit'] ?? 0.0);
                $discountPercent = $saleType->isCash() ? $cashDiscount : $creditDiscount;

                $itemModel = new RequestItem([
                    'package_id' => $packageId,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $count,
                    'item_discount' => $discountPercent,
                    'item_cash_discount' => $cashDiscount,
                    'item_credit_discount' => $creditDiscount,
                ]);
                $lineTotal = $itemModel->calculateLineTotal();

                $totalAmount += $lineTotal;

                $itemsData[] = [
                    'package_id' => $packageId,
                    'voucher_price' => $voucherPrice,
                    'voucher_count' => $count,
                    'item_discount' => $discountPercent,
                    'item_cash_discount' => $cashDiscount,
                    'item_credit_discount' => $creditDiscount,
                    'item_total_amount' => $lineTotal,
                ];
            }

            /** @var RequestModel $createdRequest */
            $createdRequest = $this->requestRepository->create([
                'network_id' => $networkId,
                'pos_id' => $posId,
                'sale_type' => $saleType,
                'request_total_amount' => $totalAmount,
                'request_date' => now('Asia/Aden'),
                'status' => RequestStatus::PENDING,
                'created_by' => $userId,
                'request_note' => $note,
            ]);

            $this->requestItemRepository->createManyForRequest($createdRequest->request_id, $itemsData);

            $createdRequest->load(['network', 'pos', 'createdBy', 'items.voucherPackage']);

            // إشعار صاحب الشبكة بطلب الكروت الجديد عبر الإشعارات وFCM Push
            if ($network && $network->approved_by) {
                $posName = $pos?->pos_name ?? 'نقطة البيع';
                $formattedAmount = number_format($totalAmount, 2);
                $this->notificationService->sendDirect(
                    userId: (int) $network->approved_by,
                    type: NotificationType::VOUCHER_REQUEST_CREATED,
                    push: true,
                    title: '📥 طلب كروت جديد',
                    message: "قمت نقطة بيع ({$posName}) بإنشاء طلب كروت رقم #{$createdRequest->request_uuid} بإجمالي {$formattedAmount} ر.ي."
                );
            }

            return $createdRequest;
        });
    }

    /**
     * تعديل طلب الكروت قبل اعتماده أو رفضه.
     * يمنع منعاً باتاً تعديل أي طلب ليس في حالة قيد الانتظار (PENDING).
     */
    public function update(int $requestId, UpdateRequestRequest $request, int $userId): RequestModel
    {
        return DB::transaction(function () use ($requestId, $request): RequestModel {
            /** @var RequestModel $requestModel */
            $requestModel = $this->requestRepository->findByIdForUpdate($requestId);

            if (! $requestModel->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن تعديل هذا الطلب لأنه ليس في حالة قيد الانتظار (PENDING).'],
                ]);
            }

            $updateData = [];

            if ($request->getSaleType()) {
                $updateData['sale_type'] = $request->getSaleType();
            }

            if ($request->has('request_note')) {
                $updateData['request_note'] = $request->getRequestNote();
            }

            if ($request->hasItems()) {
                $items = $request->getItems() ?? [];
                $networkId = (int) $requestModel->network_id;
                $posId = (int) $requestModel->pos_id;
                $saleType = $request->getSaleType() ?? $requestModel->sale_type;

                $totalAmount = 0.0;
                $itemsData = [];

                foreach ($items as $item) {
                    $packageId = (int) $item['package_id'];
                    $count = (int) $item['voucher_count'];

                    $package = $this->voucherPackageRepository->findById($packageId);

                    if ((int) $package->network_id !== $networkId) {
                        throw ValidationException::withMessages([
                            'items' => ["الباقة ({$package->package_name}) لا تنتمي لمزود الشبكة المحدد."],
                        ]);
                    }

                    $package->ensureActive();

                    // SECURITY: use catalog price & server effective discount (ignore client inputs)
                    $voucherPrice = (float) $package->package_voucher_price;
                    $discountData = $this->voucherPackageDiscountService->getEffectiveDiscount($networkId, $posId, $packageId);
                    $discountPercent = $saleType->isCash()
                        ? (float) ($discountData['discount_cash'] ?? 0.0)
                        : (float) ($discountData['discount_credit'] ?? 0.0);

                    $itemModel = new RequestItem([
                        'package_id' => $packageId,
                        'voucher_price' => $voucherPrice,
                        'voucher_count' => $count,
                        'item_discount' => $discountPercent,
                    ]);
                    $lineTotal = $itemModel->calculateLineTotal();

                    $totalAmount += $lineTotal;

                    $itemsData[] = [
                        'package_id' => $packageId,
                        'voucher_price' => $voucherPrice,
                        'voucher_count' => $count,
                        'item_discount' => $discountPercent,
                        'item_total_amount' => $lineTotal,
                    ];
                }

                $this->requestItemRepository->deleteByRequestId($requestId);
                $this->requestItemRepository->createManyForRequest($requestId, $itemsData);

                $updateData['request_total_amount'] = $totalAmount;
            }

            if (! empty($updateData)) {
                $this->requestRepository->update($requestId, $updateData);
            }

            return $this->getById($requestId);
        });
    }

    /**
     * احتساب ومعاينة تعديلات المراجعة والتسعير السيادي دون توليد فواتير أو تعديل الطلب الأصلي أو المساس بالأرصدة.
     *
     * @param  array<int, array{package_id: int, voucher_count: int, pricing_token?: string|null}>  $items
     * @return array<string, mixed>
     */
    public function previewModification(int $requestId, int $userId, array $items, ?SaleType $saleType = null): array
    {
        /** @var RequestModel $request */
        $request = $this->requestRepository->findById($requestId);

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن معاينة تعديل هذا الطلب لأنه ليس في حالة قيد الانتظار (PENDING).'],
            ]);
        }

        // التحقق من صلاحية مراجع الطلب وتبعيته لمزود الشبكة
        $reviewer = $this->userRepository->findById($userId);
        if ($reviewer && ! $reviewer->isSuperAdmin()) {
            $isAuthorized = ($reviewer->network && (int) $reviewer->network->network_id === (int) $request->network_id)
                || ($reviewer->networks()->where('networks.network_id', $request->network_id)->exists())
                || (method_exists($reviewer, 'networkUsers') && $reviewer->networkUsers()->where('network_id', $request->network_id)->where('status', 'ACTIVE')->exists());

            if (! $isAuthorized) {
                throw ValidationException::withMessages([
                    'network_id' => ['غير مصرح لك بمراجعة وتعديل طلبات كروت لا تنتمي لمزود الشبكة الخاص بك.'],
                ]);
            }
        }

        if ($saleType !== null && $saleType !== $request->sale_type) {
            throw ValidationException::withMessages([
                'sale_type' => ['لا يُسمح بتعديل نوع البيع (كاش/آجل) المتفق عليه في الطلب الأصلي من قِبل الشبكة.'],
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['يجب تحديد باقة واحدة على الأقل لمعاينة التعديلات.'],
            ]);
        }

        return $this->pricingReviewService->calculatePreview($request, $items, $request->sale_type);
    }

    /**
     * اعتماد طلب الكروت وتوليد فاتورة المبيعات وتسوية الرصيد آلياً بالنمط المرن.
     * يدعم تمرير تعديلات اختيارية على نوع البيع والبنود دون تعديل سجل الطلب الأصلي.
     *
     * @return array{request: RequestModel, invoice: SaleInvoice}
     */
    public function approve(int $requestId, int $reviewedBy, ApproveRequestRequest|string|null $requestData = null): array
    {
        return DB::transaction(function () use ($requestId, $reviewedBy, $requestData): array {
            /** @var RequestModel $request */
            $request = $this->requestRepository->findByIdForUpdate($requestId);

            // الحماية من التكرار والتزامن (Idempotency check): إذا كان معتمداً ولديه فاتورة مسبقاً، إعادة النتيجة المخزنة
            if ($request->isApproved()) {
                $existingInvoice = $request->invoice ?? SaleInvoice::where('request_id', $requestId)->first();
                if ($existingInvoice) {
                    return [
                        'request' => $request->load(['network', 'pos', 'createdBy', 'reviewedBy', 'items.voucherPackage']),
                        'invoice' => $existingInvoice->load(['network', 'pos', 'createdBy', 'items.voucherPackage']),
                    ];
                }
            }

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن اعتماد هذا الطلب لأنه ليس في حالة قيد الانتظار (PENDING).'],
                ]);
            }

            // التحقق من تبعية مراجع الطلب لنفس الشبكة المزودة صاحبة الطلب لمنع هجمات IDOR
            $reviewer = $this->userRepository->findById($reviewedBy);
            if ($reviewer && ! $reviewer->isSuperAdmin()) {
                $isAuthorized = ($reviewer->network && (int) $reviewer->network->network_id === (int) $request->network_id)
                    || ($reviewer->networks()->where('networks.network_id', $request->network_id)->exists())
                    || (method_exists($reviewer, 'networkUsers') && $reviewer->networkUsers()->where('network_id', $request->network_id)->where('status', 'ACTIVE')->exists());

                if (! $isAuthorized) {
                    throw ValidationException::withMessages([
                        'network_id' => ['غير مصرح لك باعتماد طلبات كروت لا تنتمي لمزود الشبكة الخاص بك.'],
                    ]);
                }
            }

            $note = null;
            $overrideItems = null;

            if ($requestData instanceof ApproveRequestRequest) {
                if ($requestData->has('sale_type')) {
                    throw ValidationException::withMessages([
                        'sale_type' => ['لا يُسمح بتعديل نوع البيع (كاش/آجل) المتفق عليه في الطلب الأصلي من قِبل الشبكة.'],
                    ]);
                }
                $note = $requestData->getRequestNote();
                $overrideItems = $requestData->hasItems() ? $requestData->getItems() : null;
            } elseif (is_string($requestData)) {
                $note = $requestData;
            }

            // جمع بيانات البنود الأصلية لسجل التدقيق الأمني
            $originalItemsSummary = $request->items()->get()->map(fn ($item) => [
                'package_id' => $item->package_id,
                'voucher_count' => $item->voucher_count,
                'voucher_price' => (float) $item->voucher_price,
                'item_discount' => (float) $item->item_discount,
                'item_total_amount' => (float) $item->item_total_amount,
            ])->toArray();

            // توليد الفاتورة والخصم من محفظة الوكيل والإيداع في محفظة الشبكة ذرياً (دون تعديل بنود الطلب الأصلي وبنفس نمط البيع المتفق عليه)
            $invoice = $this->saleInvoiceService->generateFromRequest(
                $request,
                $reviewedBy,
                null,
                $overrideItems
            );

            // تحديث حالة الطلب الأصلي إلى معتمد (APPROVED) وتوثيق تاريخ وملاحظة المراجعة مع بقاء بنوده كما طُلبت دون تغيير
            $this->requestRepository->updateStatus(
                $requestId,
                RequestStatus::APPROVED,
                $reviewedBy,
                $note ?? $request->request_note
            );

            $request->refresh()->load(['network', 'pos', 'createdBy', 'reviewedBy', 'items.voucherPackage']);

            // توثيق سجل التدقيق الشامل في system_logs
            $finalItemsSummary = $invoice->items()->get()->map(fn ($item) => [
                'package_id' => $item->package_id,
                'voucher_count' => $item->voucher_count,
                'voucher_price' => (float) $item->voucher_price,
                'item_discount' => (float) $item->item_discount,
                'item_total_amount' => (float) $item->item_total_amount,
            ])->toArray();

            SystemLoggerService::log(
                module: 'voucher_requests',
                action: 'approved',
                entityType: 'Request',
                entityId: $request->request_id,
                oldData: [
                    'request_total_amount' => (float) $request->request_total_amount,
                    'sale_type' => $request->sale_type->value,
                    'items' => $originalItemsSummary,
                ],
                newData: [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_uuid' => $invoice->invoice_uuid,
                    'invoice_total_amount' => (float) $invoice->invoice_total_amount,
                    'sale_type' => $invoice->sale_type->value,
                    'items' => $finalItemsSummary,
                    'has_overrides' => ! empty($overrideItems),
                ],
                userId: $reviewedBy
            );

            // إشعار صاحب الطلب (نقطة البيع) باعتماد الطلب وتوليد الفاتورة عبر الإشعارات وFCM Push
            if ($request->created_by) {
                $invoiceNum = $invoice->invoice_uuid ?? $invoice->invoice_id;
                $this->notificationService->sendDirect(
                    userId: $reviewedBy,
                    type: NotificationType::VOUCHER_REQUEST_APPROVED,
                    push: true,
                    title: '✅ اعتماد طلب الكروت',
                    message: "تم اعتماد طلب الكروت رقم #{$request->request_uuid} بنجاح، وتوليد فاتورة مبيعات رقم #{$invoiceNum}."
                );
            }

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
            $request = $this->requestRepository->findById($requestId, ['*'], true);

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن رفض هذا الطلب لأنه ليس في حالة قيد الانتظار (PENDING).'],
                ]);
            }

            // التحقق من تبعية مراجع الطلب لنفس الشبكة المزودة صاحبة الطلب لمنع هجمات IDOR
            $reviewer = $this->userRepository->findById($reviewedBy);
            if ($reviewer) {
                $isAuthorized = ($reviewer->network && (int) $reviewer->network->network_id === (int) $request->network_id)
                    || ($reviewer->networks()->where('networks.network_id', $request->network_id)->exists())
                    || (method_exists($reviewer, 'networkUsers') && $reviewer->networkUsers()->where('network_id', $request->network_id)->where('status', 'ACTIVE')->exists());

                if (! $isAuthorized) {
                    throw ValidationException::withMessages([
                        'network_id' => ['غير مصرح لك برفض طلبات كروت لا تنتمي لمزود الشبكة الخاص بك.'],
                    ]);
                }
            }

            $currentNote = (string) $request->request_note;
            $updatedNote = trim($currentNote." | سبب الرفض: {$reason}", ' |');

            $this->requestRepository->updateStatus(
                $requestId,
                RequestStatus::REJECTED,
                $reviewedBy,
                $updatedNote
            );

            // إشعار صاحب الطلب برفض الطلب عبر الإشعارات وFCM Push
            if ($request->created_by) {
                $this->notificationService->sendDirect(
                    userId: $reviewedBy,
                    type: NotificationType::VOUCHER_REQUEST_REJECTED,
                    push: true,
                    title: '❌ رفض طلب الكروت',
                    message: "تم رفض طلب الكروت رقم #{$request->request_uuid}. سبب الرفض: {$reason}"
                );
            }

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
            $request = $this->requestRepository->findById($requestId, ['*'], true);

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن إلغاء هذا الطلب لأنه تم البت فيه مسبقاً.'],
                ]);
            }

            $canceller = $this->userRepository->findById($userId);
            $isCreator = (int) $request->created_by === $userId;
            $isPosAuthorized = $canceller && (($canceller->pos && (int) $canceller->pos->pos_id === (int) $request->pos_id)
                || (method_exists($canceller, 'posUsers') && $canceller->posUsers()->where('pos_id', $request->pos_id)->where('status', 'ACTIVE')->exists()));

            if (! $isCreator && ! $isPosAuthorized) {
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

            // إشعار صاحب الشبكة بإلغاء الطلب عبر الإشعارات وFCM Push
            if ($request->network && $request->network->approved_by) {
                $this->notificationService->sendDirect(
                    userId: $userId,
                    type: NotificationType::VOUCHER_REQUEST_CANCELLED,
                    push: true,
                    title: 'ℹ️ إلغاء طلب كروت',
                    message: "تم إلغاء طلب الكروت رقم #{$request->request_uuid} من قبل مقدم الطلب."
                );
            }

            return $this->getById($requestId);
        });
    }

    /**
     * حذف طلب مؤقتاً (Soft Delete).
     * يمنع منعاً باتاً حذف الطلبات المعتمدة أو المرفوضة للحفاظ على نزاهة التدقيق المالي.
     */
    public function deleteRequest(int $requestId): bool
    {
        return DB::transaction(function () use ($requestId): bool {
            /** @var RequestModel $request */
            $request = $this->requestRepository->findByIdForUpdate($requestId);

            if (! $request->isPending() && ! $request->isCancelled()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن حذف طلب كروت تم اعتماده أو رفضه مسبقاً.'],
                ]);
            }

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
     * يمنع منعاً باتاً الحذف النهائي للطلبات المعتمدة أو المرفوضة.
     */
    public function forceDeleteRequest(int $requestId): bool
    {
        return DB::transaction(function () use ($requestId): bool {
            /** @var RequestModel $request */
            $request = $this->requestRepository->findTrashedById($requestId);

            if ($request && ! $request->isPending() && ! $request->isCancelled()) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن الحذف النهائي لطلب كروت تم اعتماده أو رفضه مسبقاً.'],
                ]);
            }

            $this->requestItemRepository->deleteByRequestId($requestId);

            return (bool) $this->requestRepository->forceDelete($requestId);
        });
    }

    /**
     * الحذف المجمع للطلبات مع فحص الحالات المحظورة.
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            foreach ($ids as $id) {
                /** @var RequestModel $request */
                $request = $this->requestRepository->findByIdForUpdate((int) $id);
                if (! $request->isPending() && ! $request->isCancelled()) {
                    throw ValidationException::withMessages([
                        'ids' => ["الطلب رقم #{$request->request_uuid} تم اعتماده أو رفضه ولا يمكن حذفه."],
                    ]);
                }
            }

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
     * الحذف النهائي المجمع للطلبات مع فحص الحالات المحظورة.
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            foreach ($ids as $id) {
                /** @var RequestModel $request */
                $request = $this->requestRepository->findTrashedById((int) $id);
                if ($request && ! $request->isPending() && ! $request->isCancelled()) {
                    throw ValidationException::withMessages([
                        'ids' => ["الطلب رقم #{$request->request_uuid} تم اعتماده أو رفضه ولا يمكن حذفه نهائياً."],
                    ]);
                }
                $this->requestItemRepository->deleteByRequestId((int) $id);
            }

            return $this->requestRepository->bulkForceDelete($ids);
        });
    }
}
