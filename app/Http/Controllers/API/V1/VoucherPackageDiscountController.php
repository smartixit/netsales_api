<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Network\StoreVoucherPackageDiscountRequest;
use App\Http\Requests\API\V1\Network\UpdateVoucherPackageDiscountRequest;
use App\Http\Resources\API\V1\Network\VoucherPackageDiscountResource;
use App\Models\VoucherPackageDiscount;
use App\Services\TenantAuthorizationService;
use App\Services\VoucherPackageDiscountService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة الخصومات والعمولات المخصصة لنقاط البيع (Voucher Package Discount Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال الـ FormRequest وتمريره مباشرة إلى VoucherPackageDiscountService.
 * 2. التحقق الصارم من سياسات وعزل المستأجرين (Tenant Isolation & Authorization).
 * 3. تغليف النتيجة عبر ApiResponseTrait ومورد VoucherPackageDiscountResource.
 * 4. خالٍ تماماً من الاستعلامات المباشرة، شروط الأعمال، أو DB::transaction.
 */
#[OA\Tag(name: 'Package Discounts', description: 'إدارة وتخصيص نسب الخصم الاستثنائية لنقاط البيع على باقات الكروت والعمليات المجمعة')]
class VoucherPackageDiscountController extends BaseController
{
    public function __construct(
        protected VoucherPackageDiscountService $discountService,
        protected TenantAuthorizationService $tenantAuth,
    ) {}

    /**
     * عرض قائمة الخصومات المخصصة مع الفلترة والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/voucher-package-discounts',
        summary: 'عرض قائمة الخصومات المخصصة للباقات',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'package_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الخصومات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $filters = $request->only(['network_id', 'pos_id', 'package_id', 'status', 'search', 'per_page', 'all']);

            if ($user && ! $this->tenantAuth->isSuperAdmin($user)) {
                $scope = $this->tenantAuth->getDiscountVisibilityScope($user);
                $authorizedNetworkIds = $scope['managed_network_ids'] ?? [];
                $authorizedPosIds = $scope['pos_ids'] ?? [];
                $linkedNetworkIds = $this->tenantAuth->getUserLinkedNetworkIdsForPos($user);

                if (empty($authorizedNetworkIds) && empty($authorizedPosIds)) {
                    return $this->errorResponse('عذراً، ليس لديك صلاحية عرض الخصومات.', [], 403);
                }

                if ($request->filled('pos_id')) {
                    $requestedPosId = (int) $request->input('pos_id');
                    if (empty($authorizedNetworkIds) && ! in_array($requestedPosId, $authorizedPosIds, true)) {
                        return $this->errorResponse('عذراً، لا يمكنك عرض خصومات نقطة بيع أخرى.', [], 403);
                    }
                    $filters['pos_id'] = $requestedPosId;
                }

                if ($request->filled('network_id')) {
                    $requestedNetId = (int) $request->input('network_id');
                    $allAllowedNetIds = array_values(array_unique(array_merge($authorizedNetworkIds, $linkedNetworkIds)));
                    if (! in_array($requestedNetId, $allAllowedNetIds, true)) {
                        return $this->errorResponse('عذراً، ليس لديك الصلاحية لعرض خصومات هذه الشبكة.', [], 403);
                    }
                    $filters['network_id'] = $requestedNetId;
                }

                $filters['discount_visibility_scope'] = $scope;
            }

            $discounts = $this->discountService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة الخصومات المخصصة بنجاح.', VoucherPackageDiscountResource::collection($discounts));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الخصومات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل خصم محدد.
     */
    #[OA\Get(
        path: '/api/v1/voucher-package-discounts/{id}',
        summary: 'عرض تفاصيل خصم محدد',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع بيانات الخصم بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح بعرض هذا الخصم'),
            new OA\Response(response: 404, description: 'الخصم غير موجود'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $discount = $this->discountService->findById($id);
            if (! $discount) {
                return $this->errorResponse('سجل الخصم المحدد غير موجود.', [], 404);
            }

            $user = $this->currentUser();
            if ($user && ! Gate::forUser($user)->allows('view', $discount)) {
                return $this->errorResponse('عذراً، غير مصرح لك بعرض هذا الخصم.', [], 403);
            }

            return $this->successResponse('تم جلب بيانات الخصم بنجاح.', new VoucherPackageDiscountResource($discount));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب بيانات الخصم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء خصم مخصص جديد لنقطة بيع على باقة معينة.
     */
    #[OA\Post(
        path: '/api/v1/voucher-package-discounts',
        summary: 'تخصيص خصم جديد لنقطة بيع',
        tags: ['Package Discounts'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id', 'package_id', 'pos_id', 'discount_cash', 'discount_credit'],
                    properties: [
                        new OA\Property(property: 'network_id', type: 'integer', example: 1),
                        new OA\Property(property: 'package_id', type: 'integer', example: 1),
                        new OA\Property(property: 'pos_id', type: 'integer', example: 1),
                        new OA\Property(property: 'discount_cash', type: 'number', example: 100.5),
                        new OA\Property(property: 'discount_credit', type: 'number', example: 100.5),
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'discount_start_date', type: 'string', format: 'date-time', nullable: true, description: 'تاريخ بدء سريان الخصم (اختياري - الافتراضي هو الوقت الحالي)'),
                        new OA\Property(property: 'discount_end_date', type: 'string', format: 'date-time', nullable: true, description: 'تاريخ انتهاء سريان الخصم (اختياري - فارغ يعني غير محدد الأجل)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الخصم بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح بتخصيص خصم في هذه الشبكة'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function store(StoreVoucherPackageDiscountRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $networkId = (int) $request->getNetworkId();
            $posId = (int) $request->getPosId();
            $packageId = (int) $request->getPackageId();

            if ($user && ! $this->tenantAuth->canCreateDiscount($user, $networkId, $posId, $packageId)) {
                return $this->errorResponse('عذراً، ليس لديك الصلاحية لتخصيص خصم في هذه الشبكة.', [], 403);
            }

            $discount = $this->discountService->create($request, $this->currentUserId());

            return $this->successResponse('تم تخصيص الخصم لنقطة البيع بنجاح.', new VoucherPackageDiscountResource($discount), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تخصيص الخصم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات خصم مخصص قائم.
     */
    #[OA\Put(
        path: '/api/v1/voucher-package-discounts/{id}',
        summary: 'تحديث بيانات خصم مخصص',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [],
                    properties: [
                        new OA\Property(property: 'discount_cash', type: 'number', example: 100.5),
                        new OA\Property(property: 'discount_credit', type: 'number', example: 100.5),
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'discount_start_date', type: 'string', format: 'date-time', nullable: true, description: 'تاريخ بدء سريان الخصم (اختياري - الافتراضي هو الوقت الحالي)'),
                        new OA\Property(property: 'discount_end_date', type: 'string', format: 'date-time', nullable: true, description: 'تاريخ انتهاء سريان الخصم (اختياري - فارغ يعني غير محدد الأجل)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث بيانات الخصم بنجاح'),
            new OA\Response(response: 404, description: 'الخصم غير موجود'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function update(UpdateVoucherPackageDiscountRequest $request, int $id): JsonResponse
    {
        try {
            $discount = $this->discountService->findById($id);
            if (! $discount) {
                return $this->errorResponse('سجل الخصم المحدد غير موجود في النظام.', [], 404);
            }

            $user = $this->currentUser();
            if ($user && ! Gate::forUser($user)->allows('update', $discount)) {
                return $this->errorResponse('عذراً، غير مصرح لك بتعديل هذا الخصم.', [], 403);
            }

            $discount = $this->discountService->update($id, $request);

            return $this->successResponse('تم تحديث بيانات الخصم بنجاح.', new VoucherPackageDiscountResource($discount));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث بيانات الخصم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المؤقت لخصم (Soft Delete).
     */
    #[OA\Delete(
        path: '/api/v1/voucher-package-discounts/{id}',
        summary: 'حذف خصم مخصص (مؤقت)',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم نقل الخصم لسلة المحذوفات بنجاح'),
            new OA\Response(response: 404, description: 'الخصم غير موجود'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $discount = $this->discountService->findById($id);
            if (! $discount) {
                return $this->errorResponse('سجل الخصم غير موجود أو تعذر حذفه.', [], 404);
            }

            $user = $this->currentUser();
            if ($user && ! Gate::forUser($user)->allows('delete', $discount)) {
                return $this->errorResponse('عذراً، غير مصرح لك بحذف هذا الخصم.', [], 403);
            }

            $deleted = $this->discountService->delete($id);
            if (! $deleted) {
                return $this->errorResponse('سجل الخصم غير موجود أو تعذر حذفه.', [], 404);
            }

            return $this->successResponse('تم نقل سجل الخصم لسلة المحذوفات بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل حذف الخصم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض سلة محذوفات الخصومات المخصصة.
     */
    #[OA\Get(
        path: '/api/v1/voucher-package-discounts/trashed',
        summary: 'عرض سلة محذوفات الخصومات',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع المحذوفات بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $filters = $request->only(['network_id', 'pos_id', 'package_id', 'search', 'per_page', 'all']);

            if ($user && ! $this->tenantAuth->isSuperAdmin($user)) {
                $scope = $this->tenantAuth->getDiscountVisibilityScope($user);
                $authorizedNetworkIds = $scope['managed_network_ids'] ?? [];
                $authorizedPosIds = $scope['pos_ids'] ?? [];
                $linkedNetworkIds = $this->tenantAuth->getUserLinkedNetworkIdsForPos($user);

                if (empty($authorizedNetworkIds) && empty($authorizedPosIds)) {
                    return $this->errorResponse('عذراً، ليس لديك صلاحية عرض المحذوفات.', [], 403);
                }

                if ($request->filled('pos_id')) {
                    $requestedPosId = (int) $request->input('pos_id');
                    if (empty($authorizedNetworkIds) && ! in_array($requestedPosId, $authorizedPosIds, true)) {
                        return $this->errorResponse('عذراً، لا يمكنك عرض محذوفات نقطة بيع أخرى.', [], 403);
                    }
                    $filters['pos_id'] = $requestedPosId;
                }

                if ($request->filled('network_id')) {
                    $requestedNetId = (int) $request->input('network_id');
                    $allAllowedNetIds = array_values(array_unique(array_merge($authorizedNetworkIds, $linkedNetworkIds)));
                    if (! in_array($requestedNetId, $allAllowedNetIds, true)) {
                        return $this->errorResponse('عذراً، ليس لديك الصلاحية لعرض محذوفات هذه الشبكة.', [], 403);
                    }
                    $filters['network_id'] = $requestedNetId;
                }

                $filters['discount_visibility_scope'] = $scope;
            }

            $perPage = (int) $request->input('per_page', 15);
            $trashed = $this->discountService->getTrashed($perPage, $filters);

            return $this->successResponse('تم استرجاع سلة محذوفات الخصومات بنجاح.', VoucherPackageDiscountResource::collection($trashed));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب سلة المحذوفات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة خصم محذوف مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/voucher-package-discounts/{id}/restore',
        summary: 'استعادة خصم محذوف',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تمت استعادة الخصم بنجاح'),
            new OA\Response(response: 404, description: 'الخصم غير موجود في المحذوفات'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $discount = VoucherPackageDiscount::onlyTrashed()->find($id);
            if (! $discount) {
                return $this->errorResponse('تعذر استعادة الخصم، قد لا يكون في سلة المحذوفات.', [], 404);
            }

            $user = $this->currentUser();
            if ($user && ! Gate::forUser($user)->allows('restore', $discount)) {
                return $this->errorResponse('عذراً، غير مصرح لك باستعادة هذا الخصم.', [], 403);
            }

            $restored = $this->discountService->restore($id);
            if (! $restored) {
                return $this->errorResponse('تعذر استعادة الخصم، قد لا يكون في سلة المحذوفات.', [], 404);
            }

            return $this->successResponse('تمت استعادة الخصم المخصص بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل استعادة الخصم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لخصم من قاعدة البيانات.
     */
    #[OA\Delete(
        path: '/api/v1/voucher-package-discounts/{id}/force',
        summary: 'الحذف النهائي لخصم مخصص',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح بالحذف النهائي لهذا الخصم'),
            new OA\Response(response: 404, description: 'الخصم غير موجود'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $discount = VoucherPackageDiscount::withTrashed()->find($id);
            if (! $discount) {
                return $this->errorResponse('سجل الخصم غير موجود.', [], 404);
            }

            $user = $this->currentUser();
            if ($user && ! Gate::forUser($user)->allows('forceDelete', $discount)) {
                return $this->errorResponse('عذراً، غير مصرح لك بالحذف النهائي لهذا الخصم.', [], 403);
            }

            $deleted = $this->discountService->forceDelete($id);
            if (! $deleted) {
                return $this->errorResponse('تعذر الحذف النهائي لسجل الخصم.', [], 422);
            }

            return $this->successResponse('تم الحذف النهائي لسجل الخصم بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف النهائي لسجل الخصم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع لعدة خصومات.
     */
    #[OA\Post(
        path: '/api/v1/voucher-package-discounts/batch-delete',
        summary: 'حذف مجمع للخصومات',
        tags: ['Package Discounts'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                        new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف المجمع بنجاح'),
        ]
    )]
    public function batchDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $ids = $request->getIds();
            $user = $this->currentUser();

            $discounts = VoucherPackageDiscount::whereIn('discount_id', $ids)->get();
            if ($discounts->count() !== count(array_unique($ids))) {
                return $this->errorResponse('بعض سجلات الخصم المحددة غير موجودة في النظام.', [], 404);
            }

            if ($user && ! $user->isSuperAdmin()) {
                foreach ($discounts as $disc) {
                    if (! Gate::forUser($user)->allows('delete', $disc)) {
                        return $this->errorResponse('عذراً، غير مصرح لك بحذف بعض سجلات الخصم المحددة.', [], 403);
                    }
                }
            }

            $count = $this->discountService->bulkDelete($ids);

            return $this->successResponse("تم نقل {$count} سجل خصم إلى سلة المحذوفات بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف المجمع للخصومات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة لعدة خصومات من سلة المحذوفات.
     */
    #[OA\Post(
        path: '/api/v1/voucher-package-discounts/batch-restore',
        summary: 'استعادة مجمعة للخصومات',
        tags: ['Package Discounts'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                        new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تمت الاستعادة المجمعة بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح باستعادة بعض الخصومات'),
            new OA\Response(response: 404, description: 'بعض الخصومات غير موجودة في سلة المحذوفات'),
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $ids = $request->getIds();
            $user = $this->currentUser();

            $discounts = VoucherPackageDiscount::onlyTrashed()->whereIn('discount_id', $ids)->get();
            if ($discounts->count() !== count(array_unique($ids))) {
                return $this->errorResponse('بعض سجلات الخصم المحددة غير موجودة في سلة المحذوفات.', [], 404);
            }

            if ($user && ! $user->isSuperAdmin()) {
                foreach ($discounts as $disc) {
                    if (! Gate::forUser($user)->allows('restore', $disc)) {
                        return $this->errorResponse('عذراً، غير مصرح لك باستعادة بعض سجلات الخصم المحددة.', [], 403);
                    }
                }
            }

            $count = $this->discountService->bulkRestore($ids);

            return $this->successResponse("تمت استعادة {$count} سجل خصم بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الاستعادة المجمعة للخصومات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي مجمع لعدة خصومات.
     */
    #[OA\Post(
        path: '/api/v1/voucher-package-discounts/batch-force-delete',
        summary: 'حذف نهائي مجمع للخصومات',
        tags: ['Package Discounts'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                        new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي المجمع بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح بالحذف النهائي لبعض الخصومات'),
            new OA\Response(response: 404, description: 'بعض الخصومات غير موجودة'),
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $ids = $request->getIds();
            $user = $this->currentUser();

            $discounts = VoucherPackageDiscount::withTrashed()->whereIn('discount_id', $ids)->get();
            if ($discounts->count() !== count(array_unique($ids))) {
                return $this->errorResponse('بعض سجلات الخصم المحددة غير موجودة في النظام.', [], 404);
            }

            if ($user && ! $user->isSuperAdmin()) {
                foreach ($discounts as $disc) {
                    if (! Gate::forUser($user)->allows('forceDelete', $disc)) {
                        return $this->errorResponse('عذراً، غير مصرح لك بالحذف النهائي لبعض سجلات الخصم المحددة.', [], 403);
                    }
                }
            }

            $count = $this->discountService->bulkForceDelete($ids);

            return $this->successResponse("تم الحذف النهائي لـ {$count} سجل خصم بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف النهائي المجمع للخصومات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تبديل حالة تفعيل الخصم.
     */
    #[OA\Post(
        path: '/api/v1/voucher-package-discounts/{id}/toggle-status',
        summary: 'تبديل حالة تفعيل الخصم',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تبديل الحالة بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح بتعديل هذا الخصم'),
            new OA\Response(response: 404, description: 'الخصم غير موجود'),
        ]
    )]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $discount = $this->discountService->findById($id);
            if (! $discount) {
                return $this->errorResponse('سجل الخصم المحدد غير موجود.', [], 404);
            }

            $user = $this->currentUser();
            if ($user && ! Gate::forUser($user)->allows('update', $discount)) {
                return $this->errorResponse('عذراً، غير مصرح لك بتعديل حالة هذا الخصم.', [], 403);
            }

            $discount = $this->discountService->toggleStatus($id);

            return $this->successResponse('تم تبديل حالة تفعيل الخصم بنجاح.', new VoucherPackageDiscountResource($discount));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تبديل حالة تفعيل الخصم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * احتساب الخصم الفعلي المطبق لنقطة بيع على باقة معينة.
     */
    #[OA\Get(
        path: '/api/v1/voucher-package-discounts/effective',
        summary: 'احتساب الخصم الفعلي المطبق لنقطة بيع على باقة',
        tags: ['Package Discounts'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'package_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم احتساب الخصم الفعلي بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح باحتساب الخصم لنطاق خارج الصلاحيات'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function effectiveDiscount(Request $request): JsonResponse
    {
        try {
            $networkId = (int) $request->input('network_id');
            $posId = (int) $request->input('pos_id');
            $packageId = (int) $request->input('package_id');

            if (! $networkId || ! $posId || ! $packageId) {
                return $this->errorResponse('يجب تحديد معرف الشبكة ونقطة البيع والباقة لاحتساب الخصم.', [], 422);
            }

            $user = $this->currentUser();
            if ($user) {
                $this->tenantAuth->authorizeEffectiveDiscount($user, $networkId, $posId, $packageId);
            }

            $effective = $this->discountService->getEffectiveDiscount($networkId, $posId, $packageId);

            return $this->successResponse('تم احتساب الخصم الفعلي بنجاح.', $effective);
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), [], 403);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل احتساب الخصم الفعلي: '.$e->getMessage(), [], 422);
        }
    }
}
