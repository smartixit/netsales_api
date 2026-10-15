<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Geography\StoreGovernorateRequest;
use App\Http\Requests\API\V1\Geography\UpdateGovernorateRequest;
use App\Http\Resources\API\V1\Geography\GovernorateResource;
use App\Services\GovernorateService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة المحافظات (Governorate Controller).
 *
 * متحكم نحيف صارم (Strict Lean Controller) يلتزم بالمعايير:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة مع ApiResponseTrait.
 * 3. حظر كتابة أي استعلامات مباشرة أو DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Governorates', description: 'إدارة المحافظات والتقسيمات الجغرافية الرئيسية')]
class GovernorateController extends BaseController
{
    public function __construct(
        protected GovernorateService $governorateService
    ) {}

    /**
     * عرض قائمة المحافظات مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/governorates',
        summary: 'عرض قائمة المحافظات',

        tags: ['Governorates'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة المحافظات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'per_page', 'all']);
            $governorates = $this->governorateService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة المحافظات بنجاح.', GovernorateResource::collection($governorates));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة المحافظات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب كافة المحافظات مع إحصائيات المديريات والشبكات ونقاط البيع.
     */
    #[OA\Get(
        path: '/api/v1/governorates/counts',
        summary: 'المحافظات مع أعداد المديريات والشبكات',

        tags: ['Governorates'],
        
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع إحصائيات المحافظات بنجاح'),
        ]
    )]
    public function counts(): JsonResponse
    {
        try {
            $governorates = $this->governorateService->getWithCounts();

            return $this->successResponse('تم استرجاع إحصائيات المحافظات بنجاح.', GovernorateResource::collection($governorates));
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب إحصائيات المحافظات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل محافظة محددة عبر المعرف الرقمي.
     */
    #[OA\Get(
        path: '/api/v1/governorates/{id}',
        summary: 'عرض تفاصيل محافظة محددة',

        tags: ['Governorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل المحافظة بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $governorate = $this->governorateService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل المحافظة بنجاح.', new GovernorateResource($governorate));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب تفاصيل المحافظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء محافظة جديدة.
     */
    #[OA\Post(
        path: '/api/v1/governorates',
        summary: 'إنشاء محافظة جديدة',

        tags: ['Governorates'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['governorate_name'],
                    properties: [
                    new OA\Property(property: 'governorate_name', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء المحافظة بنجاح'),
        ]
    )]
    public function store(StoreGovernorateRequest $request): JsonResponse
    {
        try {
            $governorate = $this->governorateService->create($request);

            return $this->successResponse('تم إنشاء المحافظة بنجاح.', new GovernorateResource($governorate), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء المحافظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات محافظة قائمة.
     */
    #[OA\Put(
        path: '/api/v1/governorates/{id}',
        summary: 'تحديث بيانات محافظة',

        tags: ['Governorates'],
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
                    new OA\Property(property: 'governorate_name', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث بيانات المحافظة بنجاح'),
        ]
    )]
    public function update(UpdateGovernorateRequest $request, int $id): JsonResponse
    {
        try {
            $governorate = $this->governorateService->update($request, $id);

            return $this->successResponse('تم تحديث بيانات المحافظة بنجاح.', new GovernorateResource($governorate));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث بيانات المحافظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف محافظة ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/governorates/{id}',
        summary: 'حذف محافظة ناعماً',

        tags: ['Governorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المحافظة بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->governorateService->delete($id);

            return $this->successResponse('تم حذف المحافظة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف المحافظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة المحافظات المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/governorates/trashed',
        summary: 'عرض المحافظات المحذوفة ناعماً',

        tags: ['Governorates'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع المحافظات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'per_page', 'all']);
            $governorates = $this->governorateService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع المحافظات المحذوفة بنجاح.', GovernorateResource::collection($governorates));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب المحافظات المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة محافظة محذوفة ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/governorates/{id}/restore',
        summary: 'استعادة محافظة محذوفة ناعماً',

        tags: ['Governorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة المحافظة بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $governorate = $this->governorateService->restore($id);

            return $this->successResponse('تم استعادة المحافظة بنجاح.', new GovernorateResource($governorate));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة المحافظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لمحافظة.
     */
    #[OA\Delete(
        path: '/api/v1/governorates/{id}/force',
        summary: 'الحذف النهائي لمحافظة',

        tags: ['Governorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المحافظة نهائياً بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->governorateService->forceDelete($id);

            return $this->successResponse('تم حذف المحافظة نهائياً من قاعدة البيانات.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للمحافظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع للمحافظات ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/governorates/batch-delete',
        summary: 'الحذف المجمع للمحافظات ناعماً',

        tags: ['Governorates'],
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
            new OA\Response(response: 200, description: 'تم حذف المحافظات المحددة بنجاح'),
        ]
    )]
    public function batchDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->governorateService->bulkDelete($request);

            return $this->successResponse("تم حذف [{$count}] من المحافظات بنجاح.", ['deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف المجمع للمحافظات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة للمحافظات.
     */
    #[OA\Post(
        path: '/api/v1/governorates/batch-restore',
        summary: 'الاستعادة المجمعة للمحافظات',

        tags: ['Governorates'],
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
            new OA\Response(response: 200, description: 'تم استعادة المحافظات المحددة بنجاح'),
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->governorateService->bulkRestore($request);

            return $this->successResponse("تم استعادة [{$count}] من المحافظات بنجاح.", ['restored_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الاستعادة المجمعة للمحافظات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع للمحافظات.
     */
    #[OA\Post(
        path: '/api/v1/governorates/batch-force-delete',
        summary: 'الحذف النهائي المجمع للمحافظات',

        tags: ['Governorates'],
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
            new OA\Response(response: 200, description: 'تم حذف المحافظات نهائياً بنجاح'),
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->governorateService->bulkForceDelete($request);

            return $this->successResponse("تم حذف [{$count}] من المحافظات نهائياً بنجاح.", ['force_deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي المجمع للمحافظات: '.$e->getMessage(), [], 422);
        }
    }
}
