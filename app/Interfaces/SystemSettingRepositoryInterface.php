<?php

namespace App\Interfaces;

use App\Enums\SettingCategory;
use App\Models\SystemSetting;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إعدادات النظام (System Setting Repository Interface).
 *
 * @method \App\Models\SystemSetting updateOrCreateByKey(string $key, array $data)
 */
interface SystemSettingRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب الإعدادات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * جلب الإعدادات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * البحث عن إعداد عبر المفتاح الفريد (setting_key).
     */
    public function findByKey(string $key): ?SystemSetting;

    /**
     * جلب جميع إعدادات تصنيف محدد.
     */
    public function getByCategory(SettingCategory|string $category): Collection;

    /**
     * جلب كافة إعدادات النظام من الذاكرة المؤقتة.
     */
    public function getAllCached(): Collection;

    /**
     * تفريغ الذاكرة المؤقتة للإعدادات.
     */
    public function clearCache(): void;

    /**
     * تحديث أو إنشاء إعداد عبر المفتاح الفريد.
     */
    public function updateOrCreateByKey(string $key, array $data): SystemSetting;
}
