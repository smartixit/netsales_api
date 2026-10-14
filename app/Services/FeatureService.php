<?php

namespace App\Services;

use App\Http\Requests\API\V1\Feature\StoreFeatureRequest;
use App\Http\Requests\API\V1\Feature\UpdateFeatureRequest;
use App\Interfaces\FeatureRepositoryInterface;
use App\Models\Feature;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * خدمة كتالوج ميزات النظام (Feature Catalogue Service).
 */
class FeatureService
{
    public function __construct(
        protected FeatureRepositoryInterface $featureRepository
    ) {}

    /**
     * جلب قائمة الميزات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->featureRepository->getFiltered($filters);
    }

    /**
     * جلب الميزات المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->featureRepository->getTrashedFiltered($filters);
    }

    /**
     * جلب تفاصيل ميزة محددة عبر معرفها.
     */
    public function getById(int $id): Feature
    {
        /** @var Feature|null $feature */
        $feature = $this->featureRepository->findById($id);
        if (! $feature) {
            throw ValidationException::withMessages([
                'feature_id' => ["الميزة برقم [{$id}] غير موجودة."],
            ]);
        }

        return $feature;
    }

    /**
     * إضافة ميزة جديدة في كتالوج النظام.
     */
    public function create(StoreFeatureRequest $request): Feature
    {
        $existing = $this->featureRepository->findByName($request->getFeatureName());
        if ($existing) {
            throw ValidationException::withMessages([
                'feature_name' => ['اسم الميزة مسجل مسبقاً في النظام.'],
            ]);
        }

        $featureData = [
            'feature_name' => $request->getFeatureName(),
            'feature_value_type' => $request->getFeatureValueType(),
            'feature_value' => $request->getFeatureValue(),
            'feature_desc' => $request->getFeatureDesc(),
            'status' => $request->getStatus() ?? true,
        ];

        /** @var Feature $feature */
        $feature = $this->featureRepository->create($featureData);

        return $feature;
    }

    /**
     * تحديث بيانات ميزة في النظام.
     */
    public function update(int $id, UpdateFeatureRequest $request): Feature
    {
        $this->getById($id);

        if ($request->has('feature_name')) {
            $existing = $this->featureRepository->findByName($request->getFeatureName());
            if ($existing && $existing->feature_id !== $id) {
                throw ValidationException::withMessages([
                    'feature_name' => ['اسم الميزة مسجل مسبقاً لميزة أخرى.'],
                ]);
            }
        }

        $updateData = [];
        if ($request->has('feature_name')) {
            $updateData['feature_name'] = $request->getFeatureName();
        }
        if ($request->has('feature_value_type')) {
            $updateData['feature_value_type'] = $request->getFeatureValueType();
        }
        if ($request->has('feature_value')) {
            $updateData['feature_value'] = $request->getFeatureValue();
        }
        if ($request->has('feature_desc')) {
            $updateData['feature_desc'] = $request->getFeatureDesc();
        }
        if ($request->has('status')) {
            $updateData['status'] = $request->getStatus();
        }

        if (! empty($updateData)) {
            $this->featureRepository->update($id, $updateData);
        }

        return $this->getById($id);
    }

    /**
     * تبديل حالة تفعيل الميزة (نشط / معطل).
     */
    public function toggleStatus(int $id): bool
    {
        $this->getById($id);

        return $this->featureRepository->toggleStatus($id);
    }

    /**
     * حذف ميزة ناعماً.
     */
    public function delete(int $id): bool
    {
        $this->getById($id);

        return (bool) $this->featureRepository->delete($id);
    }

    /**
     * استعادة ميزة محذوفة ناعماً.
     */
    public function restore(int $id): bool
    {
        $feature = $this->featureRepository->findTrashedById($id);
        if (! $feature) {
            throw ValidationException::withMessages([
                'feature_id' => ["الميزة المحذوفة برقم [{$id}] غير موجودة."],
            ]);
        }

        return (bool) $this->featureRepository->restore($id);
    }

    /**
     * حذف نهائي للميزة.
     */
    public function forceDelete(int $id): bool
    {
        $feature = $this->featureRepository->findTrashedById($id);
        if (! $feature) {
            throw ValidationException::withMessages([
                'feature_id' => ["الميزة المحذوفة برقم [{$id}] غير موجودة."],
            ]);
        }

        return (bool) $this->featureRepository->forceDelete($id);
    }

    /**
     * حذف مجمع ناعم للميزات.
     */
    public function batchDelete(array $ids): int
    {
        return $this->featureRepository->bulkDelete($ids);
    }

    /**
     * استعادة مجمعة للميزات.
     */
    public function batchRestore(array $ids): int
    {
        return $this->featureRepository->bulkRestore($ids);
    }

    /**
     * حذف نهائي مجمع للميزات.
     */
    public function batchForceDelete(array $ids): int
    {
        return $this->featureRepository->bulkForceDelete($ids);
    }
}
