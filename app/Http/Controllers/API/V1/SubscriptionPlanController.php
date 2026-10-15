<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\SubscriptionPlan\AttachPlanFeatureRequest;
use App\Http\Requests\API\V1\SubscriptionPlan\StoreSubscriptionPlanRequest;
use App\Http\Requests\API\V1\SubscriptionPlan\UpdateSubscriptionPlanRequest;
use App\Http\Resources\API\V1\SubscriptionPlan\SubscriptionPlanFeatureResource;
use App\Http\Resources\API\V1\SubscriptionPlan\SubscriptionPlanResource;
use App\Services\SubscriptionPlanService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة باقات الاشتراك وميزاتها (Subscription Plan Controller).
 *
 * متحكم نحيف (Strict Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة.
 * 3. عدم فتح أي معاملات DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Subscription Plans', description: 'إدارة باقات الاشتراك السحابية لمزودي الشبكات وميزاتها وحصصها')]
class SubscriptionPlanController extends BaseController
{
    public function __construct(
        protected SubscriptionPlanService $subscriptionPlanService
    ) {}

    /**
     * عرض قائمة باقات الاشتراك مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/subscription-plans',
        summary: 'عرض قائمة باقات الاشتراك',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع باقات الاشتراك بنجاح'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'per_page', 'all']);
            $plans = $this->subscriptionPlanService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة باقات الاشتراك بنجاح.', SubscriptionPlanResource::collection($plans));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة باقات الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل باقة اشتراك محددة مع ميزاتها.
     */
    #[OA\Get(
        path: '/api/v1/subscription-plans/{id}',
        summary: 'عرض تفاصيل باقة اشتراك',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الباقة بنجاح'),
            new OA\Response(response: 404, description: 'الباقة غير موجودة'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $plan = $this->subscriptionPlanService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل باقة الاشتراك بنجاح.', new SubscriptionPlanResource($plan));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات باقة الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء باقة اشتراك جديدة.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plans',
        summary: 'إنشاء باقة اشتراك جديدة',
        
        tags: ['Subscription Plans'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['plan_name', 'plan_price', 'plan_validity'],
                    properties: [
                    new OA\Property(property: 'plan_name', type: 'string'),
                    new OA\Property(property: 'plan_price', type: 'number', example: 100.5),
                    new OA\Property(property: 'plan_discount', type: 'number', example: 100.5),
                    new OA\Property(property: 'plan_validity', type: 'integer', example: 1),
                    new OA\Property(property: 'plan_desc', type: 'string'),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    new OA\Property(property: 'features[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء باقة الاشتراك بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        try {
            $userId = (int) ($request->user()?->user_id ?? 1);
            $plan = $this->subscriptionPlanService->create($request, $userId);

            return $this->successResponse('تم إنشاء باقة الاشتراك بنجاح.', new SubscriptionPlanResource($plan), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء باقة الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات باقة اشتراك قائمة وميزاتها.
     */
    #[OA\Put(
        path: '/api/v1/subscription-plans/{id}',
        summary: 'تحديث بيانات باقة اشتراك',
        
        tags: ['Subscription Plans'],
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
                    new OA\Property(property: 'plan_name', type: 'string'),
                    new OA\Property(property: 'plan_price', type: 'number', example: 100.5),
                    new OA\Property(property: 'plan_discount', type: 'number', example: 100.5),
                    new OA\Property(property: 'plan_validity', type: 'integer', example: 1),
                    new OA\Property(property: 'plan_desc', type: 'string'),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    new OA\Property(property: 'features[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث باقة الاشتراك بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function update(UpdateSubscriptionPlanRequest $request, int $id): JsonResponse
    {
        try {
            $userId = (int) ($request->user()?->user_id ?? 1);
            $plan = $this->subscriptionPlanService->update($id, $request, $userId);

            return $this->successResponse('تم تحديث بيانات باقة الاشتراك بنجاح.', new SubscriptionPlanResource($plan));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث باقة الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تبديل حالة تفعيل الباقة (نشط / معطل).
     */
    #[OA\Post(
        path: '/api/v1/subscription-plans/{id}/toggle-status',
        summary: 'تبديل حالة تفعيل باقة الاشتراك',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تبديل حالة الباقة بنجاح'),
        ]
    )]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $newStatus = $this->subscriptionPlanService->toggleStatus($id);

            return $this->successResponse('تم تبديل حالة باقة الاشتراك بنجاح.', ['status' => $newStatus]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تبديل حالة باقة الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * ربط أو تحديث ميزة في باقة معينة.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plans/{id}/features',
        summary: 'ربط أو تحديث ميزة بالباقة',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['feature_id', 'plan_feature_value'],
                    properties: [
                    new OA\Property(property: 'feature_id', type: 'integer', example: 1),
                    new OA\Property(property: 'plan_feature_value', type: 'string'),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم ربط الميزة بالباقة بنجاح'),
        ]
    )]
    public function attachFeature(AttachPlanFeatureRequest $request, int $id): JsonResponse
    {
        try {
            $userId = (int) ($request->user()?->user_id ?? 1);
            $planFeature = $this->subscriptionPlanService->attachFeature($id, $request, $userId);

            return $this->successResponse('تم ربط وتحديث الميزة بالباقة بنجاح.', new SubscriptionPlanFeatureResource($planFeature));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر ربط الميزة بالباقة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إزالة ميزة من باقة معينة.
     */
    #[OA\Delete(
        path: '/api/v1/subscription-plans/{id}/features/{featureId}',
        summary: 'إزالة ميزة من الباقة',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'featureId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تمت إزالة الميزة بنجاح'),
        ]
    )]
    public function detachFeature(int $id, int $featureId): JsonResponse
    {
        try {
            $this->subscriptionPlanService->detachFeature($id, $featureId);

            return $this->successResponse('تمت إزالة الميزة من باقة الاشتراك بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إزالة الميزة من باقة الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف باقة اشتراك ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/subscription-plans/{id}',
        summary: 'حذف باقة اشتراك ناعماً',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الباقة بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->subscriptionPlanService->delete($id);

            return $this->successResponse('تم حذف باقة الاشتراك مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف باقة الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة باقات الاشتراك المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/subscription-plans/trashed',
        summary: 'عرض باقات الاشتراك المحذوفة ناعماً',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الباقات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'per_page', 'all']);
            $plans = $this->subscriptionPlanService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة باقات الاشتراك المحذوفة بنجاح.', SubscriptionPlanResource::collection($plans));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب باقات الاشتراك المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة باقة اشتراك محذوفة ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plans/{id}/restore',
        summary: 'استعادة باقة اشتراك محذوفة',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الباقة بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->subscriptionPlanService->restore($id);

            return $this->successResponse('تم استعادة باقة الاشتراك بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة باقة الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي لباقة الاشتراك مع تنظيف ميزاتها.
     */
    #[OA\Delete(
        path: '/api/v1/subscription-plans/{id}/force',
        summary: 'حذف نهائي لباقة الاشتراك',
        
        tags: ['Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الباقة نهائياً بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->subscriptionPlanService->forceDelete($id);

            return $this->successResponse('تم حذف باقة الاشتراك نهائياً وتنظيف كافة ميزاتها بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي لباقة الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع ناعم للباقات.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plans/batch-delete',
        summary: 'حذف مجمع للباقات',
        
        tags: ['Subscription Plans'],
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
            $count = $this->subscriptionPlanService->batchDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) باقة اشتراك بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع لباقات الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة للباقات المحذوفة.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plans/batch-restore',
        summary: 'استعادة مجمعة للباقات',
        
        tags: ['Subscription Plans'],
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
            $count = $this->subscriptionPlanService->batchRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) باقة اشتراك بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة لباقات الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي مجمع للباقات.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plans/batch-force-delete',
        summary: 'حذف نهائي مجمع للباقات',
        
        tags: ['Subscription Plans'],
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
            $count = $this->subscriptionPlanService->batchForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) باقة اشتراك بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع لباقات الاشتراك: '.$e->getMessage(), [], 422);
        }
    }
}
