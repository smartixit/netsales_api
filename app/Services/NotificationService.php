<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Notification\StoreNotificationRequest;
use App\Http\Requests\API\V1\Notification\UpdateNotificationRequest;
use App\Interfaces\NotificationRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة الإشعارات والتنبيهات الموجهة (Notification Service).
 *
 * طبقة الأعمال المسؤولة حصراً عن:
 * 1. فتح وإغلاق معاملات قاعدة البيانات DB::transaction.
 * 2. التنسيق مع NotificationRepositoryInterface دون كتابة استعلامات Eloquent مباشرة.
 * 3. توفير قنوات الإرسال المباشر للخدمات الأخرى بالنظام.
 */
class NotificationService
{
    public function __construct(
        protected NotificationRepositoryInterface $notificationRepository,
        protected UserRepositoryInterface $userRepository
    ) {}

    /**
     * جلب قائمة الإشعارات العامة مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->notificationRepository->getFiltered($filters, ['user']);
    }

    /**
     * جلب قائمة الإشعارات المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->notificationRepository->getTrashedFiltered($filters, ['user']);
    }

    /**
     * جلب إشعارات المستخدم الحالي مع الفلترة والترقيم بالمؤشر.
     */
    public function getMyNotifications(int $userId, array $filters = []): Collection|CursorPaginator
    {
        return $this->notificationRepository->getForUser($userId, $filters);
    }

    /**
     * حساب عدد الإشعارات غير المقروءة للمستخدم.
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->notificationRepository->countUnreadForUser($userId);
    }

    /**
     * جلب تفاصيل إشعار محدد مع التأكد من وجوده.
     */
    public function getById(int $id): Notification
    {
        /** @var Notification|null $notification */
        $notification = $this->notificationRepository->findById($id);
        if (! $notification) {
            throw ValidationException::withMessages([
                'notification_id' => ["الإشعار رقم [{$id}] غير موجود."],
            ]);
        }

        $notification->load('user');

        return $notification;
    }

    /**
     * إنشاء إشعار جديد عبر FormRequest (للمدراء).
     */
    public function create(StoreNotificationRequest $request): Notification
    {
        return DB::transaction(function () use ($request) {
            $user = $this->userRepository->findById($request->getUserId());
            if (! $user) {
                throw ValidationException::withMessages([
                    'user_id' => ["المستخدم رقم [{$request->getUserId()}] غير موجود بالنظام."],
                ]);
            }

            $type = $request->getNotificationType();

            $data = [
                'user_id' => $request->getUserId(),
                'notification_type' => $type->value,
                'notification_title' => $request->getNotificationTitle(),
                'notification_message' => $request->getNotificationMessage(),
                'notification_image' => $request->getNotificationImage(),
                'is_read' => $request->getIsRead(),
                'read_at' => $request->getIsRead() ? ($request->getReadAt() ?? now('Asia/Aden')) : null,
            ];

            return $this->notificationRepository->create($data);
        });
    }

    /**
     * إرسال إشعار فوري مباشر لمستخدم (برمجياً من كافة خدمات النظام).
     */
    public function sendDirect(
        int $userId,
        string|NotificationType $type,
        string $title,
        string $message,
        ?string $image = null
    ): Notification {
        $typeValue = $type instanceof NotificationType ? $type->value : (string) $type;

        $data = [
            'user_id' => $userId,
            'notification_type' => $typeValue,
            'notification_title' => $title,
            'notification_message' => $message,
            'notification_image' => $image,
            'is_read' => false,
            'read_at' => null,
        ];

        return $this->notificationRepository->create($data);
    }

    /**
     * إرسال إشعار جماعي لمجموعة من المستخدمين.
     */
    public function sendBulk(
        array $userIds,
        string|NotificationType $type,
        string $title,
        string $message,
        ?string $image = null
    ): int {
        return DB::transaction(function () use ($userIds, $type, $title, $message, $image) {
            $count = 0;
            foreach ($userIds as $userId) {
                $this->sendDirect((int) $userId, $type, $title, $message, $image);
                $count++;
            }

            return $count;
        });
    }

    /**
     * تحديث بيانات إشعار قائم عبر FormRequest.
     */
    public function update(UpdateNotificationRequest $request, int $id): Notification
    {
        return DB::transaction(function () use ($request, $id) {
            $notification = $this->getById($id);

            $data = [];

            if ($request->getUserId() !== null) {
                $user = $this->userRepository->findById($request->getUserId());
                if (! $user) {
                    throw ValidationException::withMessages([
                        'user_id' => ["المستخدم رقم [{$request->getUserId()}] غير موجود بالنظام."],
                    ]);
                }
                $data['user_id'] = $request->getUserId();
            }

            if ($request->getNotificationType() !== null) {
                $data['notification_type'] = $request->getNotificationType()->value;
            }

            if ($request->getNotificationTitle() !== null) {
                $data['notification_title'] = $request->getNotificationTitle();
            }

            if ($request->getNotificationMessage() !== null) {
                $data['notification_message'] = $request->getNotificationMessage();
            }

            if ($request->has('notification_image')) {
                $data['notification_image'] = $request->getNotificationImage();
            }

            if ($request->getIsRead() !== null) {
                $data['is_read'] = $request->getIsRead();
                $data['read_at'] = $request->getIsRead() ? ($request->getReadAt() ?? now('Asia/Aden')) : null;
            }

            $this->notificationRepository->update($notification->notification_id, $data);

            return $this->getById($id);
        });
    }

    /**
     * حذف إشعار ناعماً.
     */
    public function delete(int $id): bool
    {
        $notification = $this->getById($id);

        return $this->notificationRepository->delete($notification->notification_id);
    }

    /**
     * استعادة إشعار محذوف ناعماً.
     */
    public function restore(int $id): Notification
    {
        $this->notificationRepository->restore($id);

        return $this->getById($id);
    }

    /**
     * الحذف النهائي لإشعار من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        return $this->notificationRepository->forceDelete($id);
    }

    /**
     * الحذف المجمع للإشعارات ناعماً.
     */
    public function bulkDelete(BatchIdsRequest $request): int
    {
        return $this->notificationRepository->bulkDelete($request->getIds());
    }

    /**
     * الاستعادة المجمعة للإشعارات المحذوفة ناعماً.
     */
    public function bulkRestore(BatchIdsRequest $request): int
    {
        return $this->notificationRepository->bulkRestore($request->getIds());
    }

    /**
     * الحذف النهائي المجمع للإشعارات.
     */
    public function bulkForceDelete(BatchIdsRequest $request): int
    {
        return $this->notificationRepository->bulkForceDelete($request->getIds());
    }

    /**
     * تمييز إشعار فردي كمقروء للمستخدم.
     */
    public function markAsRead(int $id, ?int $userId = null): bool
    {
        $notification = $this->getById($id);

        if ($userId !== null && (int) $notification->user_id !== $userId) {
            throw ValidationException::withMessages([
                'notification_id' => ['لا تملك صلاحية الوصول لهذا الإشعار.'],
            ]);
        }

        return $this->notificationRepository->markAsRead($id);
    }

    /**
     * تمييز جميع إشعارات المستخدم كمقروءة دفعة واحدة.
     */
    public function markAllAsRead(int $userId): int
    {
        return $this->notificationRepository->markAllAsReadForUser($userId);
    }
}
