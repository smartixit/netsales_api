<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Billing\{StoreCurrencyRequest, StorePlanFeatureRequest, StorePlanRequest, StorePriceRequest, StoreProductRequest, UpdateCurrencyRequest};
use App\Http\Resources\API\V1\Billing\{BillingPlanResource, BillingPlanFeatureResource, BillingPriceResource, CurrencyResource};
use App\Services\Billing\{BillingCatalogService, CurrencyService};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillingCatalogController extends BaseController
{
    public function __construct(
        private CurrencyService $currencies,
        private BillingCatalogService $catalog
    ) {}

    public function currencies(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع العملات النشطة.',
            CurrencyResource::collection($this->currencies->listActive())
        );
    }

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

    public function products(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع منتجات الفوترة.',
            BillingProductResource::collection($this->catalog->products(
                $request->only(['is_active', 'search', 'per_page', 'all'])
            ))
        );
    }

    public function plans(Request $request): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع كتالوج الخطط.',
            BillingPlanResource::collection($this->catalog->plans(
                $request->only(['is_active', 'search', 'per_page', 'all'])
            ))
        );
    }

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

    public function createDraftVersion(\App\Http\Requests\API\V1\Billing\CreateDraftVersionRequest $request, int $planId): JsonResponse
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
