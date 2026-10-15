<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Feature\StoreFeatureRequest;
use App\Http\Requests\API\V1\Feature\UpdateFeatureRequest;
use App\Http\Resources\API\V1\SubscriptionPlan\FeatureResource;
use App\Services\FeatureService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم كتالوج ميزات النظام (Feature Catalogue Controller).
 *
 * متحكم نحيف (Strict Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة.
 * 3. عدم فتح أي معاملات DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Features', description: 'إدارة كتالوج ميزات النظام وأنواع قيمها')]
class FeatureController extends BaseController
{
    public function __construct(
        protected FeatureService $featureService
    ) {}

    /**
     * عرض قائمة ميزات النظام مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/features',
        summary: 'عرض قائمة ميزات النظام',
        
        tags: ['Features'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'feature_value_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الميزات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'feature_value_type', 'search', 'per_page', 'all']);
            $features = $this->featureService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة ميزات النظام بنجاح.', FeatureResource::collection($features));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة ميزات النظام: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل ميزة محددة عبر معرفها.
     */
    #[OA\Get(
        path: '/api/v1/features/{id}',
        summary: 'عرض تفاصيل ميزة',
        
        tags: ['Features'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الميزة بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $feature = $this->featureService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل الميزة بنجاح.', new FeatureResource($feature));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات الميزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إضافة ميزة جديدة في كتالوج النظام.
     */
    #[OA\Post(
        path: '/api/v1/features',
        summary: 'إضافة ميزة جديدة',
        
        tags: ['Features'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['feature_name', 'feature_value_type'],
                    properties: [
                    new OA\Property(property: 'feature_name', type: 'string'),
                    new OA\Property(property: 'feature_value_type', type: 'string'),
                    new OA\Property(property: 'feature_value', type: 'string'),
                    new OA\Property(property: 'feature_desc', type: 'string'),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الميزة بنجاح'),
        ]
    )]
    public function store(StoreFeatureRequest $request): JsonResponse
    {
        try {
            $feature = $this->featureService->create($request);

            return $this->successResponse('تم إنشاء الميزة بنجاح.', new FeatureResource($feature), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء الميزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات ميزة في النظام.
     */
    #[OA\Put(
        path: '/api/v1/features/{id}',
        summary: 'تحديث بيانات ميزة',
        
        tags: ['Features'],
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
                    new OA\Property(property: 'feature_name', type: 'string'),
                    new OA\Property(property: 'feature_value_type', type: 'string'),
                    new OA\Property(property: 'feature_value', type: 'string'),
                    new OA\Property(property: 'feature_desc', type: 'string'),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث الميزة بنجاح'),
        ]
    )]
    public function update(UpdateFeatureRequest $request, int $id): JsonResponse
    {
        try {
            $feature = $this->featureService->update($id, $request);

            return $this->successResponse('تم تحديث بيانات الميزة بنجاح.', new FeatureResource($feature));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث بيانات الميزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تبديل حالة تفعيل الميزة (نشط / معطل).
     */
    #[OA\Post(
        path: '/api/v1/features/{id}/toggle-status',
        summary: 'تبديل حالة تفعيل الميزة',
        
        tags: ['Features'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تبديل حالة الميزة بنجاح'),
        ]
    )]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $newStatus = $this->featureService->toggleStatus($id);

            return $this->successResponse('تم تبديل حالة الميزة بنجاح.', ['status' => $newStatus]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تبديل حالة الميزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف ميزة ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/features/{id}',
        summary: 'حذف ميزة ناعماً',
        
        tags: ['Features'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الميزة بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->featureService->delete($id);

            return $this->successResponse('تم حذف الميزة مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف الميزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة الميزات المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/features/trashed',
        summary: 'عرض الميزات المحذوفة ناعماً',
        
        tags: ['Features'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الميزات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'per_page', 'all']);
            $features = $this->featureService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة الميزات المحذوفة بنجاح.', FeatureResource::collection($features));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الميزات المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة ميزة محذوفة ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/features/{id}/restore',
        summary: 'استعادة ميزة محذوفة',
        
        tags: ['Features'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الميزة بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->featureService->restore($id);

            return $this->successResponse('تم استعادة الميزة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة الميزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي للميزة.
     */
    #[OA\Delete(
        path: '/api/v1/features/{id}/force',
        summary: 'حذف نهائي للميزة',
        
        tags: ['Features'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الميزة نهائياً بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->featureService->forceDelete($id);

            return $this->successResponse('تم حذف الميزة نهائياً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للميزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع ناعم للميزات.
     */
    #[OA\Post(
        path: '/api/v1/features/batch-delete',
        summary: 'حذف مجمع للميزات',
        
        tags: ['Features'],
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
            $count = $this->featureService->batchDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) ميزة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع للميزات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة للميزات.
     */
    #[OA\Post(
        path: '/api/v1/features/batch-restore',
        summary: 'استعادة مجمعة للميزات',
        
        tags: ['Features'],
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
            $count = $this->featureService->batchRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) ميزة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة للميزات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي مجمع للميزات.
     */
    #[OA\Post(
        path: '/api/v1/features/batch-force-delete',
        summary: 'حذف نهائي مجمع للميزات',
        
        tags: ['Features'],
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
            $count = $this->featureService->batchForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) ميزة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع للميزات: '.$e->getMessage(), [], 422);
        }
    }
}
