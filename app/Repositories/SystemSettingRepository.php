<?php

namespace App\Repositories;

use App\Enums\SettingCategory;
use App\Interfaces\SystemSettingRepositoryInterface;
use App\Models\SystemSetting;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * مستودع إدارة إعدادات النظام (System Setting Repository).
 *
 * @property SystemSetting $model
 */
class SystemSettingRepository extends BaseRepository implements SystemSettingRepositoryInterface
{
    public const CACHE_KEY = 'system_settings_cache_all';

    public function __construct(SystemSetting $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة إعدادات النظام مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $query = $this->model->newQuery();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($filters['setting_category'])) {
            $query->category($filters['setting_category']);
        }

        if (! empty($filters['setting_value_type'])) {
            $query->where('setting_value_type', (string) $filters['setting_value_type']);
        }

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('setting_key', 'like', "%{$term}%")
                    ->orWhere('setting_desc', 'like', "%{$term}%")
                    ->orWhere('setting_value', 'like', "%{$term}%");
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('setting_category')->orderBy('setting_key')->get();
        }

        return $query->orderBy('setting_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب الإعدادات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->onlyTrashed();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($filters['setting_category'])) {
            $query->category($filters['setting_category']);
        }

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('setting_key', 'like', "%{$term}%")
                    ->orWhere('setting_desc', 'like', "%{$term}%");
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('setting_id', 'desc')->get();
        }

        return $query->orderBy('setting_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن إعداد عبر المفتاح الفريد (setting_key).
     */
    public function findByKey(string $key): ?SystemSetting
    {
        return $this->model->newQuery()
            ->byKey($key)
            ->first();
    }

    /**
     * جلب جميع إعدادات تصنيف محدد.
     */
    public function getByCategory(SettingCategory|string $category): Collection
    {
        return $this->model->newQuery()
            ->category($category)
            ->orderBy('setting_key')
            ->get();
    }

    /**
     * جلب كافة إعدادات النظام من الذاكرة المؤقتة (Cache).
     */
    public function getAllCached(): Collection
    {
        return Cache::remember(self::CACHE_KEY, 86400, function () {
            return $this->model->newQuery()->orderBy('setting_key')->get();
        });
    }

    /**
     * تفريغ الذاكرة المؤقتة للإعدادات.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function create(array $data)
    {
        $record = parent::create($data);
        $this->clearCache();

        return $record;
    }

    public function update($id, array $data)
    {
        $record = parent::update($id, $data);
        $this->clearCache();

        return $record;
    }

    public function delete($id)
    {
        $result = parent::delete($id);
        $this->clearCache();

        return $result;
    }

    public function restore($id)
    {
        $result = parent::restore($id);
        $this->clearCache();

        return $result;
    }

    public function forceDelete($id)
    {
        $result = parent::forceDelete($id);
        $this->clearCache();

        return $result;
    }

    /**
     * الحذف المجمع للإعدادات ناعماً مع التسجيل الرقابي.
     */
    public function bulkDelete(array $ids): int
    {
        $settings = $this->model->newQuery()->whereIn('setting_id', $ids)->get();
        $count = 0;

        foreach ($settings as $setting) {
            if ($setting->delete()) {
                $this->recordDeletion($setting);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkDeletion($ids);
            $this->clearCache();
        }

        return $count;
    }

    /**
     * الاستعادة المجمعة للإعدادات المحذوفة ناعماً مع التسجيل الرقابي.
     */
    public function bulkRestore(array $ids): int
    {
        $settings = $this->model->newQuery()->onlyTrashed()->whereIn('setting_id', $ids)->get();
        $count = 0;

        foreach ($settings as $setting) {
            if ($setting->restore()) {
                $this->recordRestore($setting);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkRestore($ids);
            $this->clearCache();
        }

        return $count;
    }

    /**
     * الحذف النهائي المجمع للإعدادات مع التسجيل الرقابي.
     */
    public function bulkForceDelete(array $ids): int
    {
        $settings = $this->model->newQuery()->withTrashed()->whereIn('setting_id', $ids)->get();
        $count = 0;

        foreach ($settings as $setting) {
            $data = $setting->toArray();
            if ($setting->forceDelete()) {
                $this->recordForceDeletion($setting, $data);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkForceDelete($ids);
            $this->clearCache();
        }

        return $count;
    }

    /**
     * تحديث أو إنشاء إعداد عبر المفتاح الفريد مع مسح الكاش.
     */
    public function updateOrCreateByKey(string $key, array $data): SystemSetting
    {
        /** @var SystemSetting $setting */
        $setting = $this->model->newQuery()->updateOrCreate(
            ['setting_key' => $key],
            $data
        );

        $this->clearCache();

        return $setting;
    }
}
