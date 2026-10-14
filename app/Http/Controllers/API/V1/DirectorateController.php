<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Geography\StoreDirectorateRequest;
use App\Http\Requests\API\V1\Geography\UpdateDirectorateRequest;
use App\Http\Resources\API\V1\Geography\DirectorateResource;
use App\Services\DirectorateService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة المديريات (Directorate Controller).
 *
 * متحكم نحيف صارم (Strict Lean Controller) يلتزم بالمعايير:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة مع ApiResponseTrait.
 * 3. حظر كتابة أي استعلامات مباشرة أو DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Directorates', description: 'إدارة المديريات والمناطق التابعة للمحافظات')]
class DirectorateController extends BaseController
{
    public function __construct(
        protected DirectorateService $directorateService
    ) {}

    /**
     * عرض قائمة المديريات مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/directorates',
        summary: 'عرض قائمة المديريات',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        parameters: [
            new OA\Parameter(name: 'governorate_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة المديريات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['governorate_id', 'search', 'per_page', 'all']);
            $directorates = $this->directorateService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة المديريات بنجاح.', DirectorateResource::collection($directorates));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة المديريات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض مديريات محافظة محددة.
     */
    #[OA\Get(
        path: '/api/v1/directorates/governorate/{governorateId}',
        summary: 'مديريات محافظة محددة',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        parameters: [
            new OA\Parameter(name: 'governorateId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع مديريات المحافظة بنجاح'),
        ]
    )]
    public function byGovernorate(int $governorateId): JsonResponse
    {
        try {
            $directorates = $this->directorateService->getByGovernorate($governorateId);

            return $this->successResponse("تم استرجاع مديريات المحافظة رقم [{$governorateId}] بنجاح.", DirectorateResource::collection($directorates));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب مديريات المحافظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل مديرية محددة عبر المعرف الرقمي.
     */
    #[OA\Get(
        path: '/api/v1/directorates/{id}',
        summary: 'عرض تفاصيل مديرية محددة',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل المديرية بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $directorate = $this->directorateService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل المديرية بنجاح.', new DirectorateResource($directorate));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب تفاصيل المديرية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء مديرية جديدة.
     */
    #[OA\Post(
        path: '/api/v1/directorates',
        summary: 'إنشاء مديرية جديدة',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء المديرية بنجاح'),
        ]
    )]
    public function store(StoreDirectorateRequest $request): JsonResponse
    {
        try {
            $directorate = $this->directorateService->create($request);

            return $this->successResponse('تم إنشاء المديرية بنجاح.', new DirectorateResource($directorate), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء المديرية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات مديرية قائمة.
     */
    #[OA\Put(
        path: '/api/v1/directorates/{id}',
        summary: 'تحديث بيانات مديرية',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث بيانات المديرية بنجاح'),
        ]
    )]
    public function update(UpdateDirectorateRequest $request, int $id): JsonResponse
    {
        try {
            $directorate = $this->directorateService->update($request, $id);

            return $this->successResponse('تم تحديث بيانات المديرية بنجاح.', new DirectorateResource($directorate));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث بيانات المديرية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مديرية ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/directorates/{id}',
        summary: 'حذف مديرية ناعماً',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المديرية بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->directorateService->delete($id);

            return $this->successResponse('تم حذف المديرية بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف المديرية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة المديريات المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/directorates/trashed',
        summary: 'عرض المديريات المحذوفة ناعماً',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع المديريات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['governorate_id', 'search', 'per_page', 'all']);
            $directorates = $this->directorateService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع المديريات المحذوفة بنجاح.', DirectorateResource::collection($directorates));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب المديريات المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مديرية محذوفة ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/directorates/{id}/restore',
        summary: 'استعادة مديرية محذوفة ناعماً',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة المديرية بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $directorate = $this->directorateService->restore($id);

            return $this->successResponse('تم استعادة المديرية بنجاح.', new DirectorateResource($directorate));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة المديرية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لمديرية.
     */
    #[OA\Delete(
        path: '/api/v1/directorates/{id}/force',
        summary: 'الحذف النهائي لمديرية',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المديرية نهائياً بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->directorateService->forceDelete($id);

            return $this->successResponse('تم حذف المديرية نهائياً من قاعدة البيانات.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للمديرية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع للمديريات ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/directorates/batch-delete',
        summary: 'الحذف المجمع للمديريات ناعماً',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المديريات المحددة بنجاح'),
        ]
    )]
    public function batchDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->directorateService->bulkDelete($request);

            return $this->successResponse("تم حذف [{$count}] من المديريات بنجاح.", ['deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف المجمع للمديريات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة للمديريات.
     */
    #[OA\Post(
        path: '/api/v1/directorates/batch-restore',
        summary: 'الاستعادة المجمعة للمديريات',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة المديريات المحددة بنجاح'),
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->directorateService->bulkRestore($request);

            return $this->successResponse("تم استعادة [{$count}] من المديريات بنجاح.", ['restored_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الاستعادة المجمعة للمديريات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع للمديريات.
     */
    #[OA\Post(
        path: '/api/v1/directorates/batch-force-delete',
        summary: 'الحذف النهائي المجمع للمديريات',
        security: [['SessionAuth' => []]],
        tags: ['Directorates'],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المديريات نهائياً بنجاح'),
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->directorateService->bulkForceDelete($request);

            return $this->successResponse("تم حذف [{$count}] من المديريات نهائياً بنجاح.", ['force_deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي المجمع للمديريات: '.$e->getMessage(), [], 422);
        }
    }
}
