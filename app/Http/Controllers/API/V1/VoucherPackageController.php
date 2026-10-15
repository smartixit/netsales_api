<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Network\StoreVoucherPackageRequest;
use App\Http\Requests\API\V1\Network\UpdateVoucherPackageRequest;
use App\Http\Resources\API\V1\Network\VoucherPackageResource;
use App\Services\VoucherPackageService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة باقات وكروت الإنترنت (Enterprise Voucher Package Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال الـ FormRequest وتمريره مباشرة إلى VoucherPackageService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد VoucherPackageResource.
 * 3. خالٍ تماماً من الاستعلامات المباشرة، شروط الأعمال، أو DB::transaction.
 */
#[OA\Tag(name: 'Voucher Packages', description: 'إدارة باقات كروت الإنترنت، الأسعار، نسب الخصم الافتراضية، والعمليات الإدارية والمجمعة')]
class VoucherPackageController extends BaseController
{
    public function __construct(
        protected VoucherPackageService $voucherPackageService,
    ) {}

    /**
     * عرض قائمة باقات الكروت مع الفلترة والبحث والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/voucher-packages',
        summary: 'عرض قائمة باقات كروت الإنترنت',
        tags: ['Voucher Packages'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الباقات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'status', 'search', 'per_page', 'all']);
            $packages = $this->voucherPackageService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة باقات الكروت بنجاح.', VoucherPackageResource::collection($packages));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الباقات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل باقة كروت محددة.
     */
    #[OA\Get(
        path: '/api/v1/voucher-packages/{id}',
        summary: 'عرض تفاصيل باقة كروت محددة',
        tags: ['Voucher Packages'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع بيانات الباقة بنجاح'),
            new OA\Response(response: 404, description: 'الباقة غير موجودة'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $package = $this->voucherPackageService->findById($id);
            if (! $package) {
                return $this->errorResponse('باقة الكروت غير موجودة في النظام.', [], 404);
            }

            return $this->successResponse('تم جلب بيانات الباقة بنجاح.', new VoucherPackageResource($package));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب بيانات الباقة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء باقة كروت جديدة.
     */
    #[OA\Post(
        path: '/api/v1/voucher-packages',
        summary: 'إنشاء باقة كروت جديدة',
        tags: ['Voucher Packages'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id', 'package_name', 'package_voucher_price', 'package_default_cash_discount', 'package_default_credit_discount'],
                    properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'package_name', type: 'string'),
                    new OA\Property(property: 'package_color', type: 'string'),
                    new OA\Property(property: 'package_balance', type: 'string'),
                    new OA\Property(property: 'package_uptime', type: 'string'),
                    new OA\Property(property: 'package_validity', type: 'string'),
                    new OA\Property(property: 'package_voucher_count', type: 'integer', example: 1),
                    new OA\Property(property: 'package_voucher_price', type: 'number', example: 100.5),
                    new OA\Property(property: 'package_default_cash_discount', type: 'number', example: 100.5),
                    new OA\Property(property: 'package_default_credit_discount', type: 'number', example: 100.5),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الباقة بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function store(StoreVoucherPackageRequest $request): JsonResponse
    {
        try {
            $package = $this->voucherPackageService->create($request, auth()->id() ?? 1);

            return $this->successResponse('تم إنشاء باقة الكروت بنجاح.', new VoucherPackageResource($package), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل إنشاء باقة الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات باقة كروت قائمة.
     */
    #[OA\Put(
        path: '/api/v1/voucher-packages/{id}',
        summary: 'تحديث بيانات باقة كروت',
        tags: ['Voucher Packages'],
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
                    new OA\Property(property: 'package_name', type: 'string'),
                    new OA\Property(property: 'package_color', type: 'string'),
                    new OA\Property(property: 'package_balance', type: 'string'),
                    new OA\Property(property: 'package_uptime', type: 'string'),
                    new OA\Property(property: 'package_validity', type: 'string'),
                    new OA\Property(property: 'package_voucher_count', type: 'integer', example: 1),
                    new OA\Property(property: 'package_voucher_price', type: 'number', example: 100.5),
                    new OA\Property(property: 'package_default_cash_discount', type: 'number', example: 100.5),
                    new OA\Property(property: 'package_default_credit_discount', type: 'number', example: 100.5),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث بيانات الباقة بنجاح'),
            new OA\Response(response: 404, description: 'الباقة غير موجودة'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function update(UpdateVoucherPackageRequest $request, int $id): JsonResponse
    {
        try {
            $package = $this->voucherPackageService->update($id, $request);

            return $this->successResponse('تم تحديث بيانات باقة الكروت بنجاح.', new VoucherPackageResource($package));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث بيانات الباقة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المؤقت لباقة كروت (Soft Delete).
     */
    #[OA\Delete(
        path: '/api/v1/voucher-packages/{id}',
        summary: 'حذف باقة كروت (مؤقت)',
        tags: ['Voucher Packages'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم نقل الباقة لسلة المحذوفات بنجاح'),
            new OA\Response(response: 404, description: 'الباقة غير موجودة'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->voucherPackageService->delete($id);
            if (! $deleted) {
                return $this->errorResponse('باقة الكروت غير موجودة أو تعذر حذفها.', [], 404);
            }

            return $this->successResponse('تم نقل باقة الكروت لسلة المحذوفات بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل حذف باقة الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض سلة محذوفات باقات الكروت.
     */
    #[OA\Get(
        path: '/api/v1/voucher-packages/trashed',
        summary: 'عرض سلة محذوفات باقات الكروت',
        tags: ['Voucher Packages'],
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
            $trashed = $this->voucherPackageService->getTrashed($perPage);

            return $this->successResponse('تم استرجاع سلة محذوفات الباقات بنجاح.', VoucherPackageResource::collection($trashed));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب سلة المحذوفات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة باقة كروت محذوفة مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/voucher-packages/{id}/restore',
        summary: 'استعادة باقة كروت محذوفة',
        tags: ['Voucher Packages'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تمت استعادة الباقة بنجاح'),
            new OA\Response(response: 404, description: 'الباقة غير موجودة في المحذوفات'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $restored = $this->voucherPackageService->restore($id);
            if (! $restored) {
                return $this->errorResponse('تعذر استعادة الباقة، قد لا تكون في سلة المحذوفات.', [], 404);
            }

            return $this->successResponse('تمت استعادة باقة الكروت بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل استعادة باقة الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لباقة كروت من قاعدة البيانات.
     */
    #[OA\Delete(
        path: '/api/v1/voucher-packages/{id}/force',
        summary: 'الحذف النهائي لباقة كروت',
        tags: ['Voucher Packages'],
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
            $deleted = $this->voucherPackageService->forceDelete($id);
            if (! $deleted) {
                return $this->errorResponse('تعذر الحذف النهائي لباقة الكروت.', [], 422);
            }

            return $this->successResponse('تم الحذف النهائي لباقة الكروت من قاعدة البيانات بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف النهائي لباقة الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع لعدة باقات كروت.
     */
    #[OA\Post(
        path: '/api/v1/voucher-packages/batch-delete',
        summary: 'حذف مجمع لباقات الكروت',
        tags: ['Voucher Packages'],
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
            $count = $this->voucherPackageService->bulkDelete($ids);

            return $this->successResponse("تم نقل {$count} باقة كروت إلى سلة المحذوفات بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف المجمع لباقات الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة لعدة باقات من سلة المحذوفات.
     */
    #[OA\Post(
        path: '/api/v1/voucher-packages/batch-restore',
        summary: 'استعادة مجمعة لباقات الكروت',
        tags: ['Voucher Packages'],
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
            $count = $this->voucherPackageService->bulkRestore($ids);

            return $this->successResponse("تمت استعادة {$count} باقة كروت بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الاستعادة المجمعة لباقات الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي مجمع لعدة باقات كروت.
     */
    #[OA\Post(
        path: '/api/v1/voucher-packages/batch-force-delete',
        summary: 'حذف نهائي مجمع لباقات الكروت',
        tags: ['Voucher Packages'],
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
            $count = $this->voucherPackageService->bulkForceDelete($ids);

            return $this->successResponse("تم الحذف النهائي لـ {$count} باقة كروت من النظام بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف النهائي المجمع لباقات الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تبديل حالة تفعيل الباقة.
     */
    #[OA\Post(
        path: '/api/v1/voucher-packages/{id}/toggle-status',
        summary: 'تبديل حالة تفعيل باقة الكروت',
        tags: ['Voucher Packages'],
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
            $package = $this->voucherPackageService->toggleStatus($id);

            return $this->successResponse('تم تبديل حالة تفعيل الباقة بنجاح.', new VoucherPackageResource($package));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تبديل حالة تفعيل الباقة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض باقات شبكة معينة.
     */
    #[OA\Get(
        path: '/api/v1/networks/{id}/voucher-packages',
        summary: 'عرض باقات شبكة معينة',
        tags: ['Voucher Packages'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع باقات الشبكة بنجاح'),
        ]
    )]
    public function networkPackages(int $id, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'per_page', 'all']);
            $packages = $this->voucherPackageService->getForNetwork($id, $filters);

            return $this->successResponse('تم استرجاع باقات الشبكة بنجاح.', VoucherPackageResource::collection($packages));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب باقات الشبكة: '.$e->getMessage(), [], 422);
        }
    }
}
