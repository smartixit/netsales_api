<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Common\RejectRequest;
use App\Http\Requests\API\V1\Pos\StorePosRequest;
use App\Http\Requests\API\V1\Pos\UpdatePosRequest;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Services\PosService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة نقاط البيع والوكلاء (Enterprise POS Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير البرمجية الصارمة:
 * 1. استقبال الـ FormRequest المحقق وتمريره مباشرة إلى PosService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد PosResource.
 * 3. خالٍ تماماً من الاستعلامات المباشرة، شروط الأعمال، أو DB::transaction.
 */
#[OA\Tag(name: 'POS', description: 'إدارة نقاط البيع، مراجعة واعتماد طلبات الوكلاء، والعمليات الإدارية المجمعة')]
class PosController extends BaseController
{
    public function __construct(
        protected PosService $posService,
    ) {}

    /**
     * عرض قائمة نقاط البيع مع الفلترة والبحث والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/pos',
        summary: 'عرض قائمة نقاط البيع',
        description: 'استرجاع قائمة نقاط البيع والوكلاء مع دعم الفلترة حسب الحالة، المحافظة، المديرية، البحث، والترقيم.',
        tags: ['POS'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ACTIVE', 'PENDING', 'SUSPENDED', 'REJECTED'])),
            new OA\Parameter(name: 'governorate_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'directorate_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة نقاط البيع بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'status', 'governorate_id', 'directorate_id', 'per_page', 'all']);
            $posList = $this->posService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة نقاط البيع بنجاح.', PosResource::collection($posList));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة نقاط البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل نقطة بيع محددة.
     */
    #[OA\Get(
        path: '/api/v1/pos/{id}',
        summary: 'عرض تفاصيل نقطة بيع',
        description: 'جلب بيانات نقطة البيع الكاملة مع المحافظة والمديرية والمحافظ.',
        tags: ['POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع بيانات نقطة البيع بنجاح'),
            new OA\Response(response: 404, description: 'نقطة البيع غير موجودة'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $pos = $this->posService->findById($id);
            if (! $pos) {
                return $this->errorResponse('نقطة البيع غير موجودة في النظام.', [], 404);
            }

            return $this->successResponse('تم جلب بيانات نقطة البيع بنجاح.', new PosResource($pos));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب بيانات نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء نقطة بيع جديدة إدارياً.
     */
    #[OA\Post(
        path: '/api/v1/pos',
        summary: 'إضافة نقطة بيع جديدة إدارياً',
        tags: ['POS'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['pos_name', 'pos_holder_name', 'pos_phone_number', 'governorate_id', 'directorate_id'],
                    properties: [
                    new OA\Property(property: 'pos_name', type: 'string'),
                    new OA\Property(property: 'pos_holder_name', type: 'string'),
                    new OA\Property(property: 'pos_phone_number', type: 'string'),
                    new OA\Property(property: 'pos_email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'governorate_id', type: 'integer', example: 1),
                    new OA\Property(property: 'directorate_id', type: 'integer', example: 1),
                    new OA\Property(property: 'pos_address', type: 'string'),
                    new OA\Property(property: 'pos_profile_logo', type: 'string', format: 'binary'),
                    new OA\Property(property: 'status', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء نقطة البيع بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function store(StorePosRequest $request): JsonResponse
    {
        try {
            $pos = $this->posService->create($request);

            return $this->successResponse('تم إنشاء نقطة البيع بنجاح.', new PosResource($pos), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل إنشاء نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات نقطة بيع قائمة.
     */
    #[OA\Put(
        path: '/api/v1/pos/{id}',
        summary: 'تحديث بيانات نقطة بيع',
        tags: ['POS'],
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
                    new OA\Property(property: 'pos_name', type: 'string'),
                    new OA\Property(property: 'pos_holder_name', type: 'string'),
                    new OA\Property(property: 'pos_phone_number', type: 'string'),
                    new OA\Property(property: 'pos_email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'governorate_id', type: 'integer', example: 1),
                    new OA\Property(property: 'directorate_id', type: 'integer', example: 1),
                    new OA\Property(property: 'pos_address', type: 'string'),
                    new OA\Property(property: 'pos_profile_logo', type: 'string', format: 'binary'),
                    new OA\Property(property: 'status', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث بيانات نقطة البيع بنجاح'),
            new OA\Response(response: 404, description: 'نقطة البيع غير موجودة'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function update(UpdatePosRequest $request, int $id): JsonResponse
    {
        try {
            $pos = $this->posService->update($id, $request);

            return $this->successResponse('تم تحديث بيانات نقطة البيع بنجاح.', new PosResource($pos));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث بيانات نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المؤقت لنقطة بيع (Soft Delete).
     */
    #[OA\Delete(
        path: '/api/v1/pos/{id}',
        summary: 'حذف نقطة بيع (مؤقت)',
        tags: ['POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم نقل نقطة البيع لسلة المحذوفات بنجاح'),
            new OA\Response(response: 404, description: 'نقطة البيع غير موجودة'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->posService->delete($id);
            if (! $deleted) {
                return $this->errorResponse('نقطة البيع غير موجودة أو تعذر حذفها.', [], 404);
            }

            return $this->successResponse('تم نقل نقطة البيع لسلة المحذوفات بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل حذف نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استرجاع قائمة نقاط البيع المحذوفة مؤقتاً.
     */
    #[OA\Get(
        path: '/api/v1/pos/trashed',
        summary: 'عرض نقاط البيع في سلة المحذوفات',
        tags: ['POS'],
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
            $trashed = $this->posService->getTrashed($perPage);

            return $this->successResponse('تم استرجاع سلة محذوفات نقاط البيع بنجاح.', PosResource::collection($trashed));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب سلة المحذوفات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة نقطة بيع محذوفة مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/pos/{id}/restore',
        summary: 'استعادة نقطة بيع محذوفة',
        tags: ['POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تمت استعادة نقطة البيع بنجاح'),
            new OA\Response(response: 404, description: 'السجل غير موجود في سلة المحذوفات'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $restored = $this->posService->restore($id);
            if (! $restored) {
                return $this->errorResponse('تعذر استعادة نقطة البيع، قد لا تكون في سلة المحذوفات.', [], 404);
            }

            return $this->successResponse('تمت استعادة نقطة البيع بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل استعادة نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لنقطة بيع من قاعدة البيانات.
     */
    #[OA\Delete(
        path: '/api/v1/pos/{id}/force',
        summary: 'الحذف النهائي لنقطة بيع',
        tags: ['POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي لنقطة البيع بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $deleted = $this->posService->forceDelete($id);
            if (! $deleted) {
                return $this->errorResponse('تعذر الحذف النهائي لنقطة البيع.', [], 422);
            }

            return $this->successResponse('تم الحذف النهائي لنقطة البيع من قاعدة البيانات بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف النهائي لنقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع لعدة نقاط بيع مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/pos/batch-delete',
        summary: 'حذف مجمع لنقاط البيع (Batch Delete)',
        tags: ['POS'],
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
            $count = $this->posService->bulkDelete($ids);

            return $this->successResponse("تم نقل {$count} نقطة بيع إلى سلة المحذوفات بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف المجمع لنقاط البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة لعدة نقاط بيع من سلة المحذوفات.
     */
    #[OA\Post(
        path: '/api/v1/pos/batch-restore',
        summary: 'استعادة مجمعة لنقاط البيع (Batch Restore)',
        tags: ['POS'],
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
            $count = $this->posService->bulkRestore($ids);

            return $this->successResponse("تمت استعادة {$count} نقطة بيع بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الاستعادة المجمعة لنقاط البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي مجمع لعدة نقاط بيع.
     */
    #[OA\Post(
        path: '/api/v1/pos/batch-force-delete',
        summary: 'حذف نهائي مجمع لنقاط البيع (Batch Force Delete)',
        tags: ['POS'],
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
            $count = $this->posService->bulkForceDelete($ids);

            return $this->successResponse("تم الحذف النهائي لـ {$count} نقطة بيع من النظام بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف النهائي المجمع لنقاط البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الموافقة على طلب تسجيل نقطة بيع وتفعيل الحساب.
     */
    #[OA\Post(
        path: '/api/v1/pos/{id}/approve',
        summary: 'اعتماد والموافقة على نقطة بيع',
        tags: ['POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تمت الموافقة وتفعيل الحساب بنجاح'),
            new OA\Response(response: 422, description: 'نقطة البيع غير موجودة'),
        ]
    )]
    public function approve(int $id): JsonResponse
    {
        try {
            $pos = $this->posService->approve($id, (int) $this->currentUserId());

            return $this->successResponse('تمت الموافقة على طلب نقطة البيع وتفعيل حسابها بنجاح.', new PosResource($pos));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل اعتماد نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * رفض طلب تسجيل نقطة بيع مع توثيق السبب.
     */
    #[OA\Post(
        path: '/api/v1/pos/{id}/reject',
        summary: 'رفض طلب نقطة بيع',
        tags: ['POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['reason'],
                    properties: [
                    new OA\Property(property: 'reason', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم رفض طلب نقطة البيع بنجاح'),
            new OA\Response(response: 422, description: 'نقطة البيع غير موجودة'),
        ]
    )]
    public function reject(RejectRequest $request, int $id): JsonResponse
    {
        try {
            $pos = $this->posService->reject($id, (int) $this->currentUserId(), (string) $request->reason);

            return $this->successResponse('تم رفض طلب نقطة البيع بنجاح.', new PosResource($pos));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل رفض نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }
}
