<?php

namespace App\Repositories;

use App\Interfaces\FeatureRepositoryInterface;
use App\Models\Feature;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة ميزات النظام (Enterprise Feature Repository).
 *
 * @property Feature $model
 */
class FeatureRepository extends BaseRepository implements FeatureRepositoryInterface
{
    public function __construct(Feature $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة الميزات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['feature_value_type'])) {
            $query->where('feature_value_type', $filters['feature_value_type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('feature_name', 'like', "%{$search}%")
                    ->orWhere('feature_desc', 'like', "%{$search}%");
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('feature_id', 'asc')->get();
        }

        return $query->orderBy('feature_id', 'asc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن ميزة عبر اسمها.
     */
    public function findByName(string $name): ?Feature
    {
        return $this->model->newQuery()->where('feature_name', $name)->first();
    }

    /**
     * تبديل حالة تفعيل الميزة بالقفل المتشائم.
     */
    public function toggleStatus(int $id): bool
    {
        /** @var Feature|null $feature */
        $feature = $this->model->newQuery()->where('feature_id', $id)->lockForUpdate()->first();
        if (! $feature) {
            return false;
        }

        $oldStatus = $feature->status;
        $newStatus = ! $oldStatus;
        $feature->status = $newStatus;
        $feature->save();

        $this->recordModification($feature, ['status' => $oldStatus]);

        return $newStatus;
    }

    /**
     * جلب كافة الميزات المفعّلة في النظام.
     */
    public function getActiveFeatures(): Collection
    {
        return $this->model->newQuery()->where('status', true)->orderBy('feature_name', 'asc')->get();
    }
}
