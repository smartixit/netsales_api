<?php

namespace App\Services;

use App\Http\Requests\API\V1\SubscriptionPlan\AttachPlanFeatureRequest;
use App\Http\Requests\API\V1\SubscriptionPlan\StoreSubscriptionPlanRequest;
use App\Http\Requests\API\V1\SubscriptionPlan\UpdateSubscriptionPlanRequest;
use App\Interfaces\FeatureRepositoryInterface;
use App\Interfaces\SubscriptionPlanFeatureRepositoryInterface;
use App\Interfaces\SubscriptionPlanRepositoryInterface;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة باقات الاشتراك وميزاتها (Subscription Plan & Feature Service).
 */
class SubscriptionPlanService
{
    public function __construct(
        protected SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        protected SubscriptionPlanFeatureRepositoryInterface $planFeatureRepository,
        protected FeatureRepositoryInterface $featureRepository
    ) {}

    /**
     * جلب قائمة باقات الاشتراك مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->subscriptionPlanRepository->getFiltered($filters, ['features', 'planFeatures.feature']);
    }

    /**
     * جلب قائمة الباقات المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->subscriptionPlanRepository->getTrashedFiltered($filters, ['features', 'planFeatures.feature']);
    }

    /**
     * جلب تفاصيل باقة محددة عبر معرفها.
     */
    public function getById(int $id): SubscriptionPlan
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->subscriptionPlanRepository->findById($id);
        if (! $plan) {
            throw ValidationException::withMessages([
                'plan_id' => ["باقة الاشتراك برقم [{$id}] غير موجودة."],
            ]);
        }

        $plan->load(['features', 'planFeatures.feature']);

        return $plan;
    }

    /**
     * جلب تفاصيل باقة عبر المعرف الفريد للأعمال (plan_uuid).
     */
    public function getByUuid(string $uuid): SubscriptionPlan
    {
        $plan = $this->subscriptionPlanRepository->findByUuid($uuid);
        if (! $plan) {
            throw ValidationException::withMessages([
                'plan_uuid' => ["باقة الاشتراك بالمعرف [{$uuid}] غير موجودة."],
            ]);
        }

        return $plan;
    }

    /**
     * إنشاء باقة اشتراك جديدة مع إمكانية ربط ميزاتها وحصصها أولياً.
     */
    public function create(StoreSubscriptionPlanRequest $request, int $userId): SubscriptionPlan
    {
        return DB::transaction(function () use ($request, $userId) {
            $planData = [
                'plan_name' => $request->getPlanName(),
                'plan_price' => $request->getPlanPrice(),
                'plan_discount' => $request->getPlanDiscount() ?? 0.00,
                'plan_validity' => $request->getPlanValidity(),
                'plan_desc' => $request->getPlanDesc(),
                'status' => $request->getStatus() ?? true,
            ];

            /** @var SubscriptionPlan $plan */
            $plan = $this->subscriptionPlanRepository->create($planData);

            // ربط الميزات الأولية إن وجدت
            $features = $request->getFeatures();
            if (! empty($features)) {
                $this->syncFeaturesInternal($plan->plan_id, $features, $userId);
            }

            return $this->getById($plan->plan_id);
        });
    }

    /**
     * تحديث بيانات باقة اشتراك قائمة وميزاتها.
     */
    public function update(int $id, UpdateSubscriptionPlanRequest $request, int $userId): SubscriptionPlan
    {
        return DB::transaction(function () use ($id, $request, $userId) {
            $plan = $this->getById($id);

            $updateData = [];
            if ($request->has('plan_name')) {
                $updateData['plan_name'] = $request->getPlanName();
            }
            if ($request->has('plan_price')) {
                $updateData['plan_price'] = $request->getPlanPrice();
            }
            if ($request->has('plan_discount')) {
                $updateData['plan_discount'] = $request->getPlanDiscount();
            }
            if ($request->has('plan_validity')) {
                $updateData['plan_validity'] = $request->getPlanValidity();
            }
            if ($request->has('plan_desc')) {
                $updateData['plan_desc'] = $request->getPlanDesc();
            }
            if ($request->has('status')) {
                $updateData['status'] = $request->getStatus();
            }

            if (! empty($updateData)) {
                $this->subscriptionPlanRepository->update($id, $updateData);
            }

            // تحديث أو مزامنة الميزات إذا تم تمريرها
            if ($request->has('features')) {
                $this->syncFeaturesInternal($id, (array) $request->getFeatures(), $userId);
            }

            return $this->getById($id);
        });
    }

    /**
     * ربط أو تحديث ميزة منفردة بباقة معينة.
     */
    public function attachFeature(int $planId, AttachPlanFeatureRequest $request, int $userId): SubscriptionPlanFeature
    {
        return DB::transaction(function () use ($planId, $request, $userId) {
            $plan = $this->getById($planId); // تأكد من وجود الباقة
            $plan->ensureActive();
            $featureId = $request->getFeatureId();

            $feature = $this->featureRepository->findById($featureId);
            if (! $feature) {
                throw ValidationException::withMessages([
                    'feature_id' => ["الميزة برقم [{$featureId}] غير موجودة."],
                ]);
            }
            $feature->ensureActive();

            $existing = $this->planFeatureRepository->findByPlanAndFeature($planId, $featureId);
            if ($existing) {
                $this->planFeatureRepository->update($existing->subscription_plan_feature_id, [
                    'plan_feature_value' => (string) $request->getPlanFeatureValue(),
                    'status' => $request->getStatus() ?? true,
                ]);

                return $existing->fresh(['feature']);
            }

            /** @var SubscriptionPlanFeature $planFeature */
            $planFeature = $this->planFeatureRepository->create([
                'plan_id' => $planId,
                'feature_id' => $featureId,
                'plan_feature_value' => (string) $request->getPlanFeatureValue(),
                'status' => $request->getStatus() ?? true,
                'created_by' => $userId,
            ]);

            return $planFeature->load('feature');
        });
    }

    /**
     * إزالة ميزة من باقة معينة.
     */
    public function detachFeature(int $planId, int $featureId): bool
    {
        return DB::transaction(function () use ($planId, $featureId) {
            $planFeature = $this->planFeatureRepository->findByPlanAndFeature($planId, $featureId);
            if (! $planFeature) {
                throw ValidationException::withMessages([
                    'feature_id' => ['الميزة غير مرتبطة بهذه الباقة.'],
                ]);
            }

            return (bool) $this->planFeatureRepository->delete($planFeature->subscription_plan_feature_id);
        });
    }

    /**
     * تبديل حالة تفعيل الباقة (نشط / معطل).
     */
    public function toggleStatus(int $id): bool
    {
        $this->getById($id);

        return $this->subscriptionPlanRepository->toggleStatus($id);
    }

    /**
     * حذف باقة ناعماً.
     */
    public function delete(int $id): bool
    {
        $this->getById($id);

        return (bool) $this->subscriptionPlanRepository->delete($id);
    }

    /**
     * استعادة باقة محذوفة ناعماً.
     */
    public function restore(int $id): bool
    {
        $plan = $this->subscriptionPlanRepository->findTrashedById($id);
        if (! $plan) {
            throw ValidationException::withMessages([
                'plan_id' => ["باقة الاشتراك المحذوفة برقم [{$id}] غير موجودة."],
            ]);
        }

        return (bool) $this->subscriptionPlanRepository->restore($id);
    }

    /**
     * حذف نهائي لباقة الاشتراك مع تنظيف ميزاتها المرتبطة.
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $plan = $this->subscriptionPlanRepository->findTrashedById($id);
            if (! $plan) {
                throw ValidationException::withMessages([
                    'plan_id' => ["باقة الاشتراك المحذوفة برقم [{$id}] غير موجودة."],
                ]);
            }

            // تنظيف ميزات الباقة
            $this->planFeatureRepository->deleteByPlanId($id);

            return (bool) $this->subscriptionPlanRepository->forceDelete($id);
        });
    }

    /**
     * حذف مجمع ناعم للباقات.
     */
    public function batchDelete(array $ids): int
    {
        return $this->subscriptionPlanRepository->bulkDelete($ids);
    }

    /**
     * استعادة مجمعة للباقات.
     */
    public function batchRestore(array $ids): int
    {
        return $this->subscriptionPlanRepository->bulkRestore($ids);
    }

    /**
     * حذف نهائي مجمع للباقات.
     */
    public function batchForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->planFeatureRepository->deleteByPlanId((int) $id);
            }

            return $this->subscriptionPlanRepository->bulkForceDelete($ids);
        });
    }

    /**
     * مزامنة الميزات الداخلية للباقة.
     */
    protected function syncFeaturesInternal(int $planId, array $features, int $userId): void
    {
        $this->planFeatureRepository->deleteByPlanId($planId);

        foreach ($features as $f) {
            $featureId = (int) ($f['feature_id'] ?? 0);
            $featureValue = (string) ($f['plan_feature_value'] ?? '0');
            $status = isset($f['status']) ? (bool) $f['status'] : true;

            if ($featureId > 0) {
                $this->planFeatureRepository->create([
                    'plan_id' => $planId,
                    'feature_id' => $featureId,
                    'plan_feature_value' => $featureValue,
                    'status' => $status,
                    'created_by' => $userId,
                ]);
            }
        }
    }
}
