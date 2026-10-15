<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\SubscriptionPlan\ConsumeFeatureRequest;
use App\Http\Requests\API\V1\SubscriptionPlan\ExtendSubscriptionRequest;
use App\Http\Resources\API\V1\SubscriptionPlan\ProviderSubscriptionResource;
use App\Http\Resources\API\V1\SubscriptionPlan\SubscriptionPlanFeatureConsumptionResource;
use App\Services\ProviderSubscriptionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة اشتراكات مزودي الشبكات ومحرك الحصص (Provider Subscription Controller).
 *
 * متحكم نحيف (Strict Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة.
 * 3. عدم فتح أي معاملات DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Provider Subscriptions', description: 'إدارة اشتراكات مزودي الشبكات وتتبع استهلاك الحصص والميزات')]
class ProviderSubscriptionController extends BaseController
{
    public function __construct(
        protected ProviderSubscriptionService $subscriptionService
    ) {}

    /**
     * عرض قائمة اشتراكات مزودي الشبكات مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/provider-subscriptions',
        summary: 'عرض قائمة اشتراكات المزودين',
        
        tags: ['Provider Subscriptions'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'plan_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'active_only', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الاشتراكات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'plan_id', 'status', 'active_only', 'per_page', 'all']);
            $subscriptions = $this->subscriptionService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة اشتراكات المزودين بنجاح.', ProviderSubscriptionResource::collection($subscriptions));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة اشتراكات المزودين: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل اشتراك محدد عبر معرفه.
     */
    #[OA\Get(
        path: '/api/v1/provider-subscriptions/{id}',
        summary: 'عرض تفاصيل اشتراك مزود',
        
        tags: ['Provider Subscriptions'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الاشتراك بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $subscription = $this->subscriptionService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل اشتراك المزود بنجاح.', new ProviderSubscriptionResource($subscription));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات اشتراك المزود: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب الاشتراك الساري والنشط حالياً لشبكة مزود معينة.
     */
    #[OA\Get(
        path: '/api/v1/provider-subscriptions/network/{networkId}/active',
        summary: 'عرض الاشتراك النشط للشبكة',
        
        tags: ['Provider Subscriptions'],
        parameters: [
            new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الاشتراك النشط بنجاح'),
        ]
    )]
    public function activeForNetwork(int $networkId): JsonResponse
    {
        try {
            $subscription = $this->subscriptionService->getActiveForNetwork($networkId);
            if (! $subscription) {
                return $this->successResponse('لا يوجد اشتراك نشط وسار لهذه الشبكة حالياً.', null);
            }

            return $this->successResponse('تم استرجاع الاشتراك النشط للشبكة بنجاح.', new ProviderSubscriptionResource($subscription));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الاشتراك النشط للشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تمديد فترة صلاحية اشتراك قائم لشبكة معينة.
     */
    #[OA\Post(
        path: '/api/v1/provider-subscriptions/{id}/extend',
        summary: 'تمديد فترة صلاحية الاشتراك',
        
        tags: ['Provider Subscriptions'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['additional_days'],
                    properties: [
                    new OA\Property(property: 'additional_days', type: 'integer', example: 1),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تمديد الاشتراك بنجاح'),
        ]
    )]
    public function extend(ExtendSubscriptionRequest $request, int $id): JsonResponse
    {
        try {
            $subscription = $this->subscriptionService->extend($id, $request->getAdditionalDays());

            return $this->successResponse('تم تمديد فترة صلاحية اشتراك المزود بنجاح.', new ProviderSubscriptionResource($subscription));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تمديد اشتراك المزود: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض سجلات استهلاك الميزات لاشتراك محدد.
     */
    #[OA\Get(
        path: '/api/v1/provider-subscriptions/{id}/consumptions',
        summary: 'عرض استهلاك ميزات الاشتراك',
        
        tags: ['Provider Subscriptions'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع سجلات الاستهلاك بنجاح'),
        ]
    )]
    public function consumptions(int $id): JsonResponse
    {
        try {
            $consumptions = $this->subscriptionService->getConsumptions($id);

            return $this->successResponse('تم استرجاع سجلات استهلاك الميزات بنجاح.', SubscriptionPlanFeatureConsumptionResource::collection($consumptions));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب سجلات استهلاك الميزات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استهلاك حصة من ميزة للشبكة والتحقق من عدم تجاوز الحد الأقصى.
     */
    #[OA\Post(
        path: '/api/v1/provider-subscriptions/network/{networkId}/consume',
        summary: 'تسجيل استهلاك ميزة للشبكة',
        
        tags: ['Provider Subscriptions'],
        parameters: [
            new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['feature_name'],
                    properties: [
                    new OA\Property(property: 'feature_name', type: 'string'),
                    new OA\Property(property: 'amount', type: 'integer', example: 1),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تسجيل الاستهلاك بنجاح'),
        ]
    )]
    public function consume(ConsumeFeatureRequest $request, int $networkId): JsonResponse
    {
        try {
            $consumption = $this->subscriptionService->consume(
                $networkId,
                $request->getFeatureName(),
                $request->getAmount()
            );

            return $this->successResponse('تم احتساب وتسجيل استهلاك الميزة بنجاح.', new SubscriptionPlanFeatureConsumptionResource($consumption));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر احتساب استهلاك الميزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تفعيل الاشتراك المجدول مبكراً للشبكة بطلب مباشر.
     */
    #[OA\Post(
        path: '/api/v1/provider-subscriptions/network/{networkId}/activate-early',
        summary: 'تفعيل الاشتراك المجدول مبكراً',
        
        tags: ['Provider Subscriptions'],
        parameters: [
            new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تفعيل الاشتراك المجدول مبكراً بنجاح'),
        ]
    )]
    public function activateEarly(int $networkId): JsonResponse
    {
        try {
            $subscription = $this->subscriptionService->activateEarlyForNetwork($networkId);

            return $this->successResponse('تم التفعيل المبكر للاشتراك المجدول بنجاح.', new ProviderSubscriptionResource($subscription));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر التفعيل المبكر للاشتراك: '.$e->getMessage(), [], 422);
        }
    }
}
