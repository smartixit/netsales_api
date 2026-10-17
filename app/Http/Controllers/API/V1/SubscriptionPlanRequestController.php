<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\SubscriptionRequest\ApproveSubscriptionPlanRequestRequest;
use App\Http\Requests\API\V1\SubscriptionRequest\RejectSubscriptionPlanRequestRequest;
use App\Http\Requests\API\V1\SubscriptionRequest\StoreSubscriptionPlanRequestRequest;
use App\Http\Resources\API\V1\SubscriptionPlan\ProviderSubscriptionResource;
use App\Http\Resources\API\V1\SubscriptionPlan\SubscriptionPlanRequestResource;
use App\Services\SubscriptionRequestService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم طلبات الاشتراك وترقية الباقات والتدقيق المالي (Subscription Plan Request Controller).
 *
 * متحكم نحيف (Strict Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة.
 * 3. عدم فتح أي معاملات DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Subscription Plan Requests', description: 'تقديم طلبات الاشتراك وترقية الباقات والتدقيق والاعتماد')]
class SubscriptionPlanRequestController extends BaseController
{
    public function __construct(
        protected SubscriptionRequestService $subscriptionRequestService
    ) {}

    /**
     * عرض قائمة طلبات الاشتراك مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/subscription-plan-requests',
        summary: 'عرض قائمة طلبات الاشتراك',
        
        tags: ['Subscription Plan Requests'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'plan_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'request_payment_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع طلبات الاشتراك بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'plan_id', 'status', 'request_payment_type', 'from_date', 'to_date', 'search', 'per_page', 'all']);
            $requests = $this->subscriptionRequestService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة طلبات الاشتراك بنجاح.', SubscriptionPlanRequestResource::collection($requests));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة طلبات الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل طلب اشتراك محدد.
     */
    #[OA\Get(
        path: '/api/v1/subscription-plan-requests/{id}',
        summary: 'عرض تفاصيل طلب اشتراك',
        
        tags: ['Subscription Plan Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الطلب بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $subRequest = $this->subscriptionRequestService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل طلب الاشتراك بنجاح.', new SubscriptionPlanRequestResource($subRequest));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات طلب الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تقديم طلب اشتراك جديد لباقة محددة.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plan-requests',
        summary: 'تقديم طلب اشتراك جديد',
        
        tags: ['Subscription Plan Requests'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id', 'plan_id', 'request_payment_type'],
                    properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'plan_id', type: 'integer', example: 1),
                    new OA\Property(property: 'request_payment_type', type: 'string'),
                    new OA\Property(property: 'account_id', type: 'integer', example: 1),
                    new OA\Property(property: 'transaction_reference', type: 'string'),
                    new OA\Property(property: 'transaction_receipt', type: 'string', format: 'binary'),
                    new OA\Property(property: 'request_note', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم تقديم طلب الاشتراك بنجاح'),
        ]
    )]
    public function store(StoreSubscriptionPlanRequestRequest $request): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $subRequest = $this->subscriptionRequestService->create($request, $userId);

            return $this->successResponse('تم تقديم طلب الاشتراك بنجاح وهو قيد المراجعة والتدقيق.', new SubscriptionPlanRequestResource($subRequest), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تقديم طلب الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * اعتماد طلب الاشتراك وتوليد الاشتراك النشط للمزود وتهيئة الحصص.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plan-requests/{id}/approve',
        summary: 'اعتماد طلب الاشتراك',
        
        tags: ['Subscription Plan Requests'],
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
                    new OA\Property(property: 'request_note', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم اعتماد الطلب وتفعيل الاشتراك بنجاح'),
        ]
    )]
    public function approve(ApproveSubscriptionPlanRequestRequest $request, int $id): JsonResponse
    {
        try {
            $reviewerId = $this->currentUserId();
            $result = $this->subscriptionRequestService->approve($id, $request, $reviewerId);

            return $this->successResponse('تم اعتماد طلب الاشتراك وتفعيل اشتراك الشبكة وتهيئة حصص الميزات بنجاح.', [
                'request' => new SubscriptionPlanRequestResource($result['request']),
                'subscription' => new ProviderSubscriptionResource($result['subscription']),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر اعتماد طلب الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * رفض طلب الاشتراك مع توثيق السبب.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plan-requests/{id}/reject',
        summary: 'رفض طلب الاشتراك',
        
        tags: ['Subscription Plan Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['rejection_reason'],
                    properties: [
                    new OA\Property(property: 'rejection_reason', type: 'string'),
                    new OA\Property(property: 'request_note', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم رفض الطلب بنجاح'),
        ]
    )]
    public function reject(RejectSubscriptionPlanRequestRequest $request, int $id): JsonResponse
    {
        try {
            $reviewerId = $this->currentUserId();
            $subRequest = $this->subscriptionRequestService->reject($id, $request, $reviewerId);

            return $this->successResponse('تم رفض طلب الاشتراك بنجاح.', new SubscriptionPlanRequestResource($subRequest));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر رفض طلب الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إلغاء طلب اشتراك معلق من قبل المستخدم.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plan-requests/{id}/cancel',
        summary: 'إلغاء طلب اشتراك معلق',
        
        tags: ['Subscription Plan Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم إلغاء الطلب بنجاح'),
        ]
    )]
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $subRequest = $this->subscriptionRequestService->cancel($id, $userId);

            return $this->successResponse('تم إلغاء طلب الاشتراك بنجاح.', new SubscriptionPlanRequestResource($subRequest));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إلغاء طلب الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف طلب اشتراك ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/subscription-plan-requests/{id}',
        summary: 'حذف طلب اشتراك ناعماً',
        
        tags: ['Subscription Plan Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الطلب بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->subscriptionRequestService->delete($id);

            return $this->successResponse('تم حذف طلب الاشتراك مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف طلب الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة طلبات الاشتراك المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/subscription-plan-requests/trashed',
        summary: 'عرض طلبات الاشتراك المحذوفة ناعماً',
        
        tags: ['Subscription Plan Requests'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'plan_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الطلبات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'plan_id', 'search', 'per_page', 'all']);
            $requests = $this->subscriptionRequestService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة طلبات الاشتراك المحذوفة بنجاح.', SubscriptionPlanRequestResource::collection($requests));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب طلبات الاشتراك المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة طلب اشتراك محذوف ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plan-requests/{id}/restore',
        summary: 'استعادة طلب اشتراك محذوف',
        
        tags: ['Subscription Plan Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الطلب بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->subscriptionRequestService->restore($id);

            return $this->successResponse('تم استعادة طلب الاشتراك بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة طلب الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي لطلب الاشتراك مع تنظيف الإيصال.
     */
    #[OA\Delete(
        path: '/api/v1/subscription-plan-requests/{id}/force',
        summary: 'حذف نهائي لطلب الاشتراك',
        
        tags: ['Subscription Plan Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الطلب نهائياً بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->subscriptionRequestService->forceDelete($id);

            return $this->successResponse('تم حذف طلب الاشتراك نهائياً وتنظيف كافة مرفقاته بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي لطلب الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع ناعم للطلبات.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plan-requests/batch-delete',
        summary: 'حذف مجمع للطلبات',
        
        tags: ['Subscription Plan Requests'],
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
            $count = $this->subscriptionRequestService->batchDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) طلب اشتراك بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع لطلبات الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة للطلبات المحذوفة.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plan-requests/batch-restore',
        summary: 'استعادة مجمعة للطلبات',
        
        tags: ['Subscription Plan Requests'],
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
            $count = $this->subscriptionRequestService->batchRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) طلب اشتراك بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة لطلبات الاشتراك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي مجمع للطلبات.
     */
    #[OA\Post(
        path: '/api/v1/subscription-plan-requests/batch-force-delete',
        summary: 'حذف نهائي مجمع للطلبات',
        
        tags: ['Subscription Plan Requests'],
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
            $count = $this->subscriptionRequestService->batchForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) طلب اشتراك وتنظيف مرفقاتهم بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع لطلبات الاشتراك: '.$e->getMessage(), [], 422);
        }
    }
}
