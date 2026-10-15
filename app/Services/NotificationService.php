<?php

namespace App\Services;

use App\Enums\DeviceType;
use App\Enums\NotificationType;
use App\Enums\UserDeviceStatus;
use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Notification\SendAdminNotificationRequest;
use App\Http\Requests\API\V1\Notification\SendUserDirectNotificationRequest;
use App\Http\Requests\API\V1\Notification\StoreNotificationRequest;
use App\Http\Requests\API\V1\Notification\UpdateFcmTokenRequest;
use App\Http\Requests\API\V1\Notification\UpdateNotificationRequest;
use App\Interfaces\NotificationRepositoryInterface;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendFcmNotificationJob;
use App\Jobs\SendSmsNotificationJob;
use App\Models\Network;
use App\Models\Notification;
use App\Models\User;
use App\Services\Support\FileUploadService;
use App\Services\Support\SMSGateService;
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
    protected SystemSettingService $systemSettingService;

    public function __construct(
        protected NotificationRepositoryInterface $notificationRepository,
        protected UserRepositoryInterface $userRepository,
        protected UserDeviceRepositoryInterface $userDeviceRepository,
        protected FileUploadService $fileUploadService,
        protected SMSGateService $smsGateService,
        ?SystemSettingService $systemSettingService = null
    ) {
        $this->systemSettingService = $systemSettingService ?? app(SystemSettingService::class);
    }

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
        ?string $image = null,
        bool $sendPush = true
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

        $notification = $this->notificationRepository->create($data);

        if ($sendPush && $this->systemSettingService->getBool('firebase_enabled', true)) {
            SendFcmNotificationJob::dispatch(
                userId: $userId,
                title: $title,
                body: $message,
                data: [
                    'notification_id' => $notification->notification_id,
                    'notification_type' => $typeValue,
                ],
                image: $image
            );
        }

        return $notification;
    }

    /**
     * إرسال إشعار جماعي لمجموعة من المستخدمين.
     */
    public function sendBulk(
        array $userIds,
        string|NotificationType $type,
        string $title,
        string $message,
        ?string $image = null,
        bool $sendPush = true
    ): int {
        return DB::transaction(function () use ($userIds, $type, $title, $message, $image, $sendPush) {
            $count = 0;
            foreach ($userIds as $userId) {
                $this->sendDirect((int) $userId, $type, $title, $message, $image, $sendPush);
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

    /**
     * إرسال إشعار لجمهور متعدد عبر قنوات مختارة (داخل التطبيق / SMS / بريد).
     */
    public function sendToAudience(SendAdminNotificationRequest $request): array
    {
        $audience = $request->getAudience();
        $channels = $request->getChannels();
        $categoryStr = $request->getCategory() ?? 'INFO';
        $type = NotificationType::tryFrom($categoryStr) ?? NotificationType::INFO;

        $title = $request->getTitle();
        $body = $request->getBody();
        $imageFile = $request->getImage();

        $imagePath = null;
        if ($imageFile) {
            $imagePath = $this->fileUploadService->uploadFile($imageFile, 'notifications');
        }

        $recipients = $this->resolveRecipients($audience);

        foreach ($recipients as $user) {
            if (in_array('in_app', $channels, true)) {
                $this->notificationRepository->create([
                    'user_id' => $user->user_id,
                    'notification_type' => $type,
                    'notification_title' => $title,
                    'notification_message' => $body,
                    'notification_image' => $imagePath,
                    'is_read' => false,
                ]);
            }

            if (in_array('sms', $channels, true) && ! empty($user->user_phone_number) && $this->systemSettingService->getBool('sms_enabled', true)) {
                SendSmsNotificationJob::dispatch(
                    phoneNumbers: $user->user_phone_number,
                    message: "{$title}\n{$body}",
                    priority: 1,
                    messageType: 'admin_broadcast'
                );
            }

            if (in_array('email', $channels, true) && ! empty($user->user_email) && $this->systemSettingService->getBool('email_enabled', true)) {
                SendEmailNotificationJob::dispatch(
                    toEmail: $user->user_email,
                    subject: $title,
                    userName: $user->user_full_name,
                    ctaLink: config('app.url'),
                    body: $body
                );
            }

            if ((in_array('fcm', $channels, true) || in_array('push', $channels, true)) && $this->systemSettingService->getBool('firebase_enabled', true)) {
                SendFcmNotificationJob::dispatch(
                    userId: $user->user_id,
                    title: $title,
                    body: $body,
                    data: [
                        'notification_type' => $type->value,
                    ],
                    image: $imagePath
                );
            }
        }

        return [
            'recipients_count' => $recipients->count(),
            'channels' => $channels,
        ];
    }

    /**
     * حل وتحديد المستخدمين المستهدفين بناءً على خيارات الجمهور.
     */
    protected function resolveRecipients(array $audience): Collection
    {
        $type = $audience['type'] ?? 'all';

        return match ($type) {
            'role' => User::where('status', 'ACTIVE')->where('role_id', $audience['role_id'] ?? null)->get(),
            'network' => $this->resolveNetworkUsers((int) ($audience['network_id'] ?? 0)),
            'users' => User::where('status', 'ACTIVE')->whereIn('user_id', (array) ($audience['user_ids'] ?? []))->get(),
            default => User::where('status', 'ACTIVE')->get(),
        };
    }

    /**
     * جلب المستخدمين المرتبطين بشبكة محددة.
     */
    protected function resolveNetworkUsers(int $networkId): Collection
    {
        $network = Network::find($networkId);
        if (! $network) {
            return new Collection();
        }

        return User::where('status', 'ACTIVE')
            ->where(function ($q) use ($network) {
                if ($network->network_phone_number) {
                    $q->where('user_phone_number', $network->network_phone_number);
                }
                if ($network->network_email) {
                    $q->orWhere('user_email', $network->network_email);
                }
                if ($network->approved_by) {
                    $q->orWhere('user_id', $network->approved_by);
                }
            })->get();
    }

    /**
     * إرسال رسالة SMS فورية لمستخدم محدد عبر SMSGate.
     */
    public function sendSmsToUser(int $userId, string $title, string $body): bool
    {
        $user = $this->userRepository->findById($userId);
        if (! $user || empty($user->user_phone_number)) {
            throw ValidationException::withMessages([
                'user_id' => ['المستخدم المحدد غير موجود أو لا يملك رقم هاتف مسجل.'],
            ]);
        }

        $message = "{$title}\n{$body}";

        return $this->smsGateService->sendSms(
            phoneNumbers: $user->user_phone_number,
            message: $message,
            priority: 1,
            messageType: 'admin_broadcast'
        );
    }

    /**
     * تسجيل أو تحديث رمز FCM للجهاز الحالي للمستخدم.
     */
    public function updateFcmToken(UpdateFcmTokenRequest $request, int $userId): bool
    {
        return DB::transaction(function () use ($request, $userId) {
            $deviceUuid = $request->getDeviceUuid();
            $fcmToken = $request->getFcmToken();
            $deviceName = $request->getDeviceName();
            $deviceTypeStr = $request->getDeviceType();
            $deviceType = $deviceTypeStr ? (DeviceType::tryFrom(strtoupper($deviceTypeStr)) ?? DeviceType::UNKNOWN) : DeviceType::UNKNOWN;

            // التأكد من وجود الجهاز أو تسجيله للمستخدم
            $device = $this->userDeviceRepository->findByUuid($userId, $deviceUuid);
            if (! $device) {
                $this->userDeviceRepository->registerOrUpdateDevice(
                    userId: $userId,
                    deviceUuid: $deviceUuid,
                    deviceName: $deviceName ?: 'Mobile Device',
                    deviceType: $deviceType,
                    isPrimary: false,
                    status: UserDeviceStatus::ACTIVE
                );
            }

            return $this->userDeviceRepository->updateFcmToken($userId, $deviceUuid, $fcmToken);
        });
    }

    /**
     * إرسال إشعار موجه ومباشر لمستخدم من الإدارة مع تحديد القنوات (FCM / In-App / SMS).
     */
    public function sendDirectToUser(SendUserDirectNotificationRequest $request): array
    {
        $userId = $request->getUserId();
        $title = $request->getTitle();
        $body = $request->getBody();
        $channels = $request->getChannels();

        $user = $this->userRepository->findById($userId);
        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => ["المستخدم رقم [{$userId}] غير موجود."],
            ]);
        }

        $sentChannels = [];

        // 1. القناة الداخلية (In-App)
        if (in_array('in_app', $channels, true)) {
            $this->notificationRepository->create([
                'user_id' => $userId,
                'notification_type' => NotificationType::INFO,
                'notification_title' => $title,
                'notification_message' => $body,
                'is_read' => false,
            ]);
            $sentChannels[] = 'in_app';
        }

        // 2. إشعار الدفع الفوري (FCM Push)
        if ((in_array('fcm', $channels, true) || in_array('push', $channels, true)) && $this->systemSettingService->getBool('firebase_enabled', true)) {
            SendFcmNotificationJob::dispatch(
                userId: $userId,
                title: $title,
                body: $body,
                data: [
                    'notification_type' => NotificationType::INFO->value,
                ]
            );
            $sentChannels[] = 'fcm';
        }

        // 3. الرسائل النصية القصيرة (SMS)
        if (in_array('sms', $channels, true) && ! empty($user->user_phone_number) && $this->systemSettingService->getBool('sms_enabled', true)) {
            SendSmsNotificationJob::dispatch(
                phoneNumbers: $user->user_phone_number,
                message: "{$title}\n{$body}",
                priority: 1,
                messageType: 'admin_broadcast'
            );
            $sentChannels[] = 'sms';
        }

        return [
            'user_id' => $userId,
            'channels' => $sentChannels,
        ];
    }
}
