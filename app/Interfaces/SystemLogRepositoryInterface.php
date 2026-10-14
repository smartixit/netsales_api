<?php

namespace App\Interfaces;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع سجلات تدقيق النظام (System Log Repository Interface).
 */
interface SystemLogRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب سجلات التدقيق المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['user']): Collection|CursorPaginator;

    /**
     * جلب سجلات التدقيق الخاصة بكيان محدد.
     */
    public function getByEntity(string $entityType, int $entityId, array $relations = ['user']): Collection;

    /**
     * جلب سجلات مستخدم محدد مع الفلترة والترقيم بالمؤشر.
     */
    public function getByUser(int $userId, array $filters = [], array $relations = ['user']): Collection|CursorPaginator;

    /**
     * جلب قائمة الوحدات الفريدة المسجلة.
     */
    public function getDistinctModules(): array;

    /**
     * جلب قائمة الإجراءات الفريدة المسجلة.
     */
    public function getDistinctActions(): array;

    /**
     * إحصائيات ونشاط سجلات التدقيق.
     */
    public function getStats(array $filters = []): array;
}
