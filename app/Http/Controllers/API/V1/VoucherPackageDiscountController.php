<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Network\StoreVoucherPackageDiscountRequest;
use App\Http\Requests\API\V1\Network\UpdateVoucherPackageDiscountRequest;
use App\Http\Resources\API\V1\Network\VoucherPackageDiscountResource;
use App\Services\VoucherPackageDiscountService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة الخصومات والعمولات المخصصة لنقاط البيع (Voucher Package Discount Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال الـ FormRequest وتمريره مباشرة إلى VoucherPackageDiscountService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد VoucherPackageDiscountResource.
 * 3. خالٍ تماماً من الاستعلامات المباشرة، شروط الأعمال، أو DB::transaction.
 */
#[OA\Tag(name: 'Package Discounts', description: 'إدارة وتخصيص نسب الخصم الاستثنائية لنقاط البيع على باقات الكروت والعمليات المجمعة')]
class VoucherPackageDiscountController extends BaseController
{
    public function __construct(
        protected VoucherPackageDiscountService $discountService,
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
            $filters = $request->only(['network_id', 'pos_id', 'package_id', 'status', 'search', 'per_page', 'all']);
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
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الخصم بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function store(StoreVoucherPackageDiscountRequest $request): JsonResponse
    {
        try {
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
            $perPage = (int) $request->input('per_page', 15);
            $trashed = $this->discountService->getTrashed($perPage);

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
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
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
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $ids = $request->getIds();
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
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $ids = $request->getIds();
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
        ]
    )]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
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

            $effective = $this->discountService->getEffectiveDiscount($networkId, $posId, $packageId);

            return $this->successResponse('تم احتساب الخصم الفعلي بنجاح.', $effective);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل احتساب الخصم الفعلي: '.$e->getMessage(), [], 422);
        }
    }
}
