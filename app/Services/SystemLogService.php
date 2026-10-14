<?php

namespace App\Services;

use App\Interfaces\SystemLogRepositoryInterface;
use App\Models\SystemLog;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة سجلات تدقيق النظام والتتبع الأمني (System Log Service).
 *
 * المسؤولة عن استعلامات سجلات التدقيق والمطابقة الأمنية والإحصائيات دون تعديل أو حذف لضمان سلامة السجلات.
 */
class SystemLogService
{
    public function __construct(
        protected SystemLogRepositoryInterface $logRepository
    ) {}

    /**
     * جلب قائمة سجلات تدقيق النظام المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->logRepository->getFiltered($filters, ['user']);
    }

    /**
     * جلب تفاصيل سجل تدقيق محدد عبر المعرف الرقمي.
     */
    public function getById(int $id): SystemLog
    {
        /** @var SystemLog|null $log */
        $log = $this->logRepository->findById($id);

        if (! $log) {
            throw ValidationException::withMessages([
                'log_id' => ["سجل التدقيق رقم [{$id}] غير موجود."],
            ]);
        }

        $log->loadMissing('user');

        return $log;
    }

    /**
     * جلب سجلات التدقيق لكيان محدد.
     */
    public function getByEntity(string $entityType, int $entityId): Collection
    {
        return $this->logRepository->getByEntity($entityType, $entityId, ['user']);
    }

    /**
     * جلب سجلات تدقيق مستخدم محدد.
     */
    public function getByUser(int $userId, array $filters = []): Collection|CursorPaginator
    {
        return $this->logRepository->getByUser($userId, $filters, ['user']);
    }

    /**
     * جلب قائمة الوحدات البرمجية المسجلة.
     */
    public function getModules(): array
    {
        return $this->logRepository->getDistinctModules();
    }

    /**
     * جلب قائمة الإجراءات المسجلة.
     */
    public function getActions(): array
    {
        return $this->logRepository->getDistinctActions();
    }

    /**
     * جلب مؤشرات وإحصائيات النشاط لسجلات التدقيق.
     */
    public function getStats(array $filters = []): array
    {
        return $this->logRepository->getStats($filters);
    }
}
