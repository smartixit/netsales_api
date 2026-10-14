<?php

namespace App\Services;

use App\Enums\SettingCategory;
use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Setting\BatchUpdateSystemSettingRequest;
use App\Http\Requests\API\V1\Setting\StoreSystemSettingRequest;
use App\Http\Requests\API\V1\Setting\UpdateSystemSettingRequest;
use App\Interfaces\SystemSettingRepositoryInterface;
use App\Models\SystemSetting;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة إعدادات النظام وتهيئة البيئة التشغيلية (System Setting Service).
 *
 * المسؤولة حصراً عن:
 * 1. فتح وإغلاق معاملات قاعدة البيانات DB::transaction.
 * 2. التنسيق مع SystemSettingRepositoryInterface دون كتابة استعلامات مباشرة على النماذج.
 * 3. توفير التخزين المؤقت اللحظي ودوال القراءة العامة والتحويل التلقائي للأنواع.
 */
class SystemSettingService
{
    public function __construct(
        protected SystemSettingRepositoryInterface $settingRepository
    ) {}

    /**
     * جلب قيمة إعداد معين من الذاكرة المؤقتة مع تحويله لنوعه البرمجي الأصلي.
     */
    public function get(string $key, mixed $default = null, ?int $centerId = null): mixed
    {
        $settings = $this->settingRepository->getAllCached();
        $setting = $settings->firstWhere('setting_key', $key);

        if (! $setting) {
            $setting = $this->settingRepository->findByKey($key);
        }

        if (! $setting) {
            return $default;
        }

        return $setting->typed_value ?? $default;
    }

    /**
     * جلب قائمة الإعدادات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->settingRepository->getFiltered($filters);
    }

    /**
     * جلب قائمة الإعدادات المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->settingRepository->getTrashedFiltered($filters);
    }

    /**
     * جلب إعدادات تصنيف محدد.
     */
    public function getByCategory(string|SettingCategory $category): Collection
    {
        return $this->settingRepository->getByCategory($category);
    }

    /**
     * جلب تفاصيل إعداد عبر معرفه الرقمي مع التحقق من وجوده.
     */
    public function getById(int $id): SystemSetting
    {
        /** @var SystemSetting|null $setting */
        $setting = $this->settingRepository->findById($id);
        if (! $setting) {
            throw ValidationException::withMessages([
                'setting_id' => ["إعداد النظام رقم [{$id}] غير موجود."],
            ]);
        }

        return $setting;
    }

    /**
     * جلب تفاصيل إعداد عبر المفتاح الفريد.
     */
    public function getByKey(string $key): SystemSetting
    {
        $setting = $this->settingRepository->findByKey($key);
        if (! $setting) {
            throw ValidationException::withMessages([
                'setting_key' => ["مفتاح الإعداد [{$key}] غير موجود بالنظام."],
            ]);
        }

        return $setting;
    }

    /**
     * إنشاء إعداد نظام جديد.
     */
    public function create(StoreSystemSettingRequest $request): SystemSetting
    {
        return DB::transaction(function () use ($request) {
            $data = [
                'setting_category' => $request->getSettingCategory()->value,
                'setting_key' => $request->getSettingKey(),
                'setting_value_type' => $request->getSettingValueType()->value,
                'setting_value' => $request->getSettingValue(),
                'setting_desc' => $request->getSettingDesc(),
            ];

            return $this->settingRepository->create($data);
        });
    }

    /**
     * تحديث بيانات إعداد نظام قائم.
     */
    public function update(UpdateSystemSettingRequest $request, int $id): SystemSetting
    {
        return DB::transaction(function () use ($request, $id) {
            $setting = $this->getById($id);

            $data = [];

            if ($request->getSettingCategory() !== null) {
                $data['setting_category'] = $request->getSettingCategory()->value;
            }

            if ($request->getSettingKey() !== null) {
                $data['setting_key'] = $request->getSettingKey();
            }

            if ($request->getSettingValueType() !== null) {
                $data['setting_value_type'] = $request->getSettingValueType()->value;
            }

            if ($request->has('setting_value')) {
                $data['setting_value'] = $request->getSettingValue();
            }

            if ($request->has('setting_desc')) {
                $data['setting_desc'] = $request->getSettingDesc();
            }

            $this->settingRepository->update($setting->setting_id, $data);

            return $this->getById($id);
        });
    }

    /**
     * تحديث قيمة إعداد مباشرة عبر المفتاح.
     */
    public function updateByKey(string $key, mixed $value): SystemSetting
    {
        return DB::transaction(function () use ($key, $value) {
            $setting = $this->getByKey($key);

            $stringValue = is_array($value) ? json_encode($value) : (string) $value;

            $this->settingRepository->update($setting->setting_id, [
                'setting_value' => $stringValue,
            ]);

            return $this->getById($setting->setting_id);
        });
    }

    /**
     * التحديث المجمع لقيم الإعدادات.
     */
    public function batchUpdate(BatchUpdateSystemSettingRequest $request): int
    {
        return DB::transaction(function () use ($request) {
            $items = $request->getSettings();
            $updatedCount = 0;

            foreach ($items as $item) {
                $key = (string) ($item['key'] ?? '');
                $value = $item['value'] ?? null;

                if (! empty($key)) {
                    $setting = $this->settingRepository->findByKey($key);
                    if ($setting) {
                        $stringValue = is_array($value) ? json_encode($value) : (string) $value;
                        $this->settingRepository->update($setting->setting_id, [
                            'setting_value' => $stringValue,
                        ]);
                        $updatedCount++;
                    }
                }
            }

            $this->clearCache();

            return $updatedCount;
        });
    }

    /**
     * حذف إعداد نظام ناعماً.
     */
    public function delete(int $id): bool
    {
        $setting = $this->getById($id);

        return $this->settingRepository->delete($setting->setting_id);
    }

    /**
     * استعادة إعداد نظام محذوف ناعماً.
     */
    public function restore(int $id): SystemSetting
    {
        $this->settingRepository->restore($id);

        return $this->getById($id);
    }

    /**
     * الحذف النهائي لإعداد نظام من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        return $this->settingRepository->forceDelete($id);
    }

    /**
     * الحذف المجمع للإعدادات ناعماً.
     */
    public function bulkDelete(BatchIdsRequest $request): int
    {
        return $this->settingRepository->bulkDelete($request->getIds());
    }

    /**
     * الاستعادة المجمعة للإعدادات المحذوفة ناعماً.
     */
    public function bulkRestore(BatchIdsRequest $request): int
    {
        return $this->settingRepository->bulkRestore($request->getIds());
    }

    /**
     * الحذف النهائي المجمع للإعدادات.
     */
    public function bulkForceDelete(BatchIdsRequest $request): int
    {
        return $this->settingRepository->bulkForceDelete($request->getIds());
    }

    /**
     * تفريغ الذاكرة المؤقتة للإعدادات.
     */
    public function clearCache(): void
    {
        $this->settingRepository->clearCache();
    }
}
