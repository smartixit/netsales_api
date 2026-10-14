<?php

namespace App\Repositories;

use App\Interfaces\NotificationRepositoryInterface;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة الإشعارات والتنبيهات (Notification Repository).
 *
 * @property Notification $model
 */
class NotificationRepository extends BaseRepository implements NotificationRepositoryInterface
{
    public function __construct(Notification $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة الإشعارات مع الفلترة والترقيم بالمؤشر والعلاقات.
     */
    public function getFiltered(
        array $filters = [],
        array $relations = ['user']
    ): Collection|CursorPaginator {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['notification_type'])) {
            $query->where('notification_type', $filters['notification_type']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('notification_title', 'like', "%{$term}%")
                    ->orWhere('notification_message', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('notification_id', 'desc')->get();
        }

        return $query->orderBy('notification_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب الإشعارات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(
        array $filters = [],
        array $relations = ['user']
    ): Collection|CursorPaginator {
        $query = $this->model->newQuery()->onlyTrashed()->with($relations);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['notification_type'])) {
            $query->where('notification_type', $filters['notification_type']);
        }

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('notification_title', 'like', "%{$term}%")
                    ->orWhere('notification_message', 'like', "%{$term}%");
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('notification_id', 'desc')->get();
        }

        return $query->orderBy('notification_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب إشعارات مستخدم محدد مع الفلترة والترقيم بالمؤشر.
     */
    public function getForUser(int $userId, array $filters = []): Collection|CursorPaginator
    {
        $filters['user_id'] = $userId;

        return $this->getFiltered($filters, []);
    }

    /**
     * حساب عدد الإشعارات غير المقروءة لمستخدم محدد.
     */
    public function countUnreadForUser(int $userId): int
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * تمييز جميع إشعارات مستخدم محدد كمقروءة.
     */
    public function markAllAsReadForUser(int $userId): int
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now('Asia/Aden'),
                'updated_at' => now('Asia/Aden'),
            ]);
    }

    /**
     * تمييز إشعار فردي كمقروء.
     */
    public function markAsRead(int $id): bool
    {
        $notification = $this->findById($id);
        if (! $notification) {
            return false;
        }

        return $notification->update([
            'is_read' => true,
            'read_at' => now('Asia/Aden'),
            'updated_at' => now('Asia/Aden'),
        ]);
    }

    /**
     * الحذف المجمع للإشعارات ناعماً مع التسجيل الرقابي.
     */
    public function bulkDelete(array $ids): int
    {
        $notifications = $this->model->newQuery()->whereIn('notification_id', $ids)->get();
        $count = 0;

        foreach ($notifications as $notification) {
            if ($notification->delete()) {
                $this->recordDeletion($notification);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkDeletion($ids);
        }

        return $count;
    }

    /**
     * الاستعادة المجمعة للإشعارات المحذوفة ناعماً مع التسجيل الرقابي.
     */
    public function bulkRestore(array $ids): int
    {
        $notifications = $this->model->newQuery()->onlyTrashed()->whereIn('notification_id', $ids)->get();
        $count = 0;

        foreach ($notifications as $notification) {
            if ($notification->restore()) {
                $this->recordRestore($notification);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkRestore($ids);
        }

        return $count;
    }

    /**
     * الحذف النهائي المجمع للإشعارات من قاعدة البيانات مع التسجيل الرقابي.
     */
    public function bulkForceDelete(array $ids): int
    {
        $notifications = $this->model->newQuery()->withTrashed()->whereIn('notification_id', $ids)->get();
        $count = 0;

        foreach ($notifications as $notification) {
            $data = $notification->toArray();
            if ($notification->forceDelete()) {
                $this->recordForceDeletion($notification, $data);
                $count++;
            }
        }

        if ($count > 0) {
            $this->recordBulkForceDelete($ids);
        }

        return $count;
    }
}
