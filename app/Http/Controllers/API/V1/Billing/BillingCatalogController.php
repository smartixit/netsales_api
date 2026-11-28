<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Billing\CreateDraftVersionRequest;
use App\Http\Requests\API\V1\Billing\StoreCurrencyRequest;
use App\Http\Requests\API\V1\Billing\StorePlanFeatureRequest;
use App\Http\Requests\API\V1\Billing\StorePlanRequest;
use App\Http\Requests\API\V1\Billing\StorePriceRequest;
use App\Http\Requests\API\V1\Billing\StoreProductRequest;
use App\Http\Requests\API\V1\Billing\UpdateCurrencyRequest;
use App\Http\Resources\API\V1\Billing\BillingPlanFeatureResource;
use App\Http\Resources\API\V1\Billing\BillingPlanResource;
use App\Http\Resources\API\V1\Billing\BillingPlanVersionResource;
use App\Http\Resources\API\V1\Billing\BillingPriceResource;
use App\Http\Resources\API\V1\Billing\BillingProductResource;
use App\Http\Resources\API\V1\Billing\CurrencyResource;
use App\Services\Billing\BillingCatalogService;
use App\Services\Billing\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class BillingCatalogController extends BaseController
{
    public function __construct(
        private CurrencyService $currencies,
        private BillingCatalogService $catalog
    ) {}

    #[OA\Get(
        path: '/api/v1/platform/billing/currencies',
        summary: 'عرض العملات المتاحة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_currencies',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function currencies(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع العملات النشطة.',
            CurrencyResource::collection($this->currencies->listActive())
        );
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/currencies',
        summary: 'إنشاء عملة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_storeCurrency',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['code', 'name', 'decimal_places'], properties: [new OA\Property(property: 'code', type: 'string'), new OA\Property(property: 'numeric_code', type: 'integer'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'native_name', type: 'string'), new OA\Property(property: 'symbol', type: 'string'), new OA\Property(property: 'decimal_places', type: 'integer'), new OA\Property(property: 'is_active', type: 'boolean')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storeCurrency(StoreCurrencyRequest $request): JsonResponse
    {
        try {
            $currency = $this->currencies->create(
                $request->only(['code', 'numeric_code', 'name', 'native_name', 'symbol', 'decimal_places', 'is_active']),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم إنشاء العملة بنجاح.', new CurrencyResource($currency), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Put(
        path: '/api/v1/platform/billing/currencies/{currencyId}',
        summary: 'تحديث بيانات عملة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_updateCurrency',
        parameters: [new OA\Parameter(name: 'currencyId', in: 'path', required: true, schema: new OA\Schema(type: 'object', properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'native_name', type: 'string'), new OA\Property(property: 'symbol', type: 'string'), new OA\Property(property: 'is_active', type: 'boolean')]))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function updateCurrency(UpdateCurrencyRequest $request, int $currencyId): JsonResponse
    {
        try {
            $currency = $this->currencies->update(
                $currencyId,
                $request->only(['name', 'native_name', 'symbol', 'is_active']),
                $this->requireCurrentUser()
            );

            return $this->successResponse('تم تحديث بيانات العملة.', new CurrencyResource($currency));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/products',
        summary: 'عرض منتجات الفوترة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_products',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function products(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع منتجات الفوترة.',
            BillingProductResource::collection($this->catalog->products(
                $request->only(['is_active', 'search', 'per_page', 'all'])
            ))
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/plans',
        summary: 'عرض خطط الاشتراك',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_plans',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function plans(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع كتالوج الخطط.',
            BillingPlanResource::collection($this->catalog->plans(
                $request->only(['is_active', 'search', 'per_page', 'all'])
            ))
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/plans/{planId}/versions',
        summary: 'عرض إصدارات خطة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_versions',
        parameters: [new OA\Parameter(name: 'planId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function versions(Request $request, int $planId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع إصدارات الخطة.',
            BillingPlanVersionResource::collection(
                $this->catalog->versionsForPlan($planId, $request->only(['status', 'per_page']))
            )
        );
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/products',
        summary: 'إنشاء منتج فوترة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_storeProduct',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['product_key', 'name'], properties: [new OA\Property(property: 'product_key', type: 'string'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'description', type: 'string'), new OA\Property(property: 'is_active', type: 'boolean')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storeProduct(StoreProductRequest $request): JsonResponse
    {
        try {
            $product = $this->catalog->createProduct(
                $request->only(['product_key', 'name', 'description', 'is_active']),
                $this->currentUserId(false)
            );

            return $this->successResponse('تم إنشاء المنتج.', [
                'product_id' => $product->product_id,
                'product_key' => $product->product_key,
                'name' => $product->name,
                'is_active' => (bool) $product->is_active,
            ], 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/plans',
        summary: 'إنشاء خطة اشتراك',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_storePlan',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['product_id', 'plan_key', 'name'], properties: [new OA\Property(property: 'product_id', type: 'integer'), new OA\Property(property: 'plan_key', type: 'string'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'description', type: 'string'), new OA\Property(property: 'is_public', type: 'boolean'), new OA\Property(property: 'is_active', type: 'boolean')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storePlan(StorePlanRequest $request): JsonResponse
    {
        try {
            $plan = $this->catalog->createPlan(
                $request->only(['product_id', 'plan_key', 'name', 'description', 'is_public', 'is_active']),
                $this->currentUserId(false)
            );

            $plan->load(['product', 'currentVersion.prices.currency', 'currentVersion.features']);

            return $this->successResponse('تم إنشاء الخطة كمسودة كتالوج.', new BillingPlanResource($plan), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/plans/{planId}/versions',
        summary: 'إنشاء مسودة إصدار خطة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_createDraftVersion',
        parameters: [new OA\Parameter(name: 'planId', in: 'path', required: true, schema: new OA\Schema(type: 'object', properties: [new OA\Property(property: 'trial_policy_version_id', type: 'integer'), new OA\Property(property: 'dunning_policy_version_id', type: 'integer')]))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function createDraftVersion(CreateDraftVersionRequest $request, int $planId): JsonResponse
    {
        try {
            $version = $this->catalog->createDraftVersion(
                $planId,
                $this->currentUserId(false),
                $request->only(['trial_policy_version_id', 'dunning_policy_version_id'])
            );

            return $this->successResponse('تم إنشاء نسخة مسودة جديدة للخطة.', [
                'plan_version_id' => $version->plan_version_id,
                'plan_id' => $version->plan_id,
                'version_number' => $version->version_number,
                'status' => $version->status->value,
                'status_label' => $version->status->label(),
            ], 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/plan-versions/{versionId}/prices',
        summary: 'إضافة سعر إلى إصدار خطة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_addPrice',
        parameters: [new OA\Parameter(name: 'versionId', in: 'path', required: true, schema: new OA\Schema(type: 'object', required: ['currency_id', 'billing_interval', 'unit_amount'], properties: [new OA\Property(property: 'currency_id', type: 'integer'), new OA\Property(property: 'billing_interval', type: 'string'), new OA\Property(property: 'interval_count', type: 'integer'), new OA\Property(property: 'unit_amount', type: 'string'), new OA\Property(property: 'setup_fee', type: 'string'), new OA\Property(property: 'tax_behavior', type: 'string')]))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function addPrice(StorePriceRequest $request, int $versionId): JsonResponse
    {
        try {
            $price = $this->catalog->addPrice(
                $versionId,
                $request->only(['currency_id', 'billing_interval', 'interval_count', 'unit_amount', 'setup_fee', 'tax_behavior']),
                $this->currentUserId(false)
            );
            $price->load('currency');

            return $this->successResponse('تمت إضافة السعر إلى نسخة الخطة.', new BillingPriceResource($price), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/plan-versions/{versionId}/features',
        summary: 'إضافة ميزة إلى إصدار خطة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_addFeature',
        parameters: [new OA\Parameter(name: 'versionId', in: 'path', required: true, schema: new OA\Schema(type: 'object', required: ['feature_key', 'name', 'feature_type', 'value'], properties: [new OA\Property(property: 'feature_key', type: 'string'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'feature_type', type: 'string'), new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'unit', type: 'string'), new OA\Property(property: 'aggregation', type: 'string'), new OA\Property(property: 'reset_period', type: 'string'), new OA\Property(property: 'overage_behavior', type: 'string'), new OA\Property(property: 'configuration', type: 'object')]))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function addFeature(StorePlanFeatureRequest $request, int $versionId): JsonResponse
    {
        try {
            $feature = $this->catalog->addFeature(
                $versionId,
                $request->only([
                    'feature_key', 'name', 'feature_type', 'value', 'unit',
                    'aggregation', 'reset_period', 'overage_behavior', 'configuration',
                ]),
                $this->currentUserId(false)
            );

            return $this->successResponse('تمت إضافة الاستحقاق إلى نسخة الخطة.', new BillingPlanFeatureResource($feature), 201);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }

    #[OA\Post(
        path: '/api/v1/platform/billing/plan-versions/{versionId}/publish',
        summary: 'نشر إصدار خطة',
        tags: ['Platform Billing'],
        operationId: 'platform_billing_publishVersion',
        parameters: [new OA\Parameter(name: 'versionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function publishVersion(int $versionId): JsonResponse
    {
        try {
            $version = $this->catalog->publishVersion($versionId, $this->currentUserId(false));
            $plan = $version->plan()->with(['product', 'currentVersion.prices.currency', 'currentVersion.features'])->firstOrFail();

            return $this->successResponse('تم نشر نسخة الخطة وتثبيت أسعارها وشروطها.', new BillingPlanResource($plan));
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }
    }
}
