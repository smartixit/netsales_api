<?php

namespace App\Interfaces;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع الإشعارات (Notification Repository Interface).
 */
interface NotificationRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب الإشعارات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['user']): Collection|CursorPaginator;

    /**
     * جلب الإشعارات المحذوفة ناعماً مع الفلترة.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['user']): Collection|CursorPaginator;

    /**
     * جلب إشعارات مستخدم محدد مع الفلترة والترقيم بالمؤشر.
     */
    public function getForUser(int $userId, array $filters = []): Collection|CursorPaginator;

    /**
     * حساب عدد الإشعارات غير المقروءة لمستخدم محدد.
     */
    public function countUnreadForUser(int $userId): int;

    /**
     * تمييز جميع إشعارات مستخدم محدد كمقروءة.
     */
    public function markAllAsReadForUser(int $userId): int;

    /**
     * تمييز إشعار فردي كمقروء.
     */
    public function markAsRead(int $id): bool;
}
