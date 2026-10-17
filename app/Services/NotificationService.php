<?php

namespace App\Services;

use App\Enums\DeviceType;
use App\Enums\NotificationType;
use App\Enums\SmsMessageType;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Notification\SendAdminNotificationRequest;
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
use Illuminate\Support\Facades\Log;
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
     * المحرك الموحد الشامل للإرسال المباشر والفردي والجماعي (Enterprise Omnichannel Dispatcher).
     *
     * يدعم الإرسال لمستخدم واحد (int) أو مجموعة مستخدمين (array) عبر القنوات المختارة بمرونة:
     * - In-App: تسجيل الإشعار في قاعدة البيانات
     * - Push: إشعار الدفع الفوري عبر FCM
     * - SMS: رسائل نصية قصيرة مع العزل التام للبوابات وفحص سياسات الشبكات
     * - Email: بريد إلكتروني رسمي للمستخدم
     *
     * @param  int|array<int>  $userId  معرف مستخدم مفرد أو مصفوفة معرفات
     * @param  NotificationType|string  $type  نوع/تصنيف الإشعار
     * @param  string  $title  عنوان الإشعار
     * @param  string  $message  نص الإشعار
     * @param  bool  $inApp  تفعيل الإشعار الداخلي (قاعدة البيانات)
     * @param  bool  $push  تفعيل إشعار الدفع الفوري (FCM Push)
     * @param  bool  $sms  تفعيل الرسائل النصية القصيرة (SMS)
     * @param  bool  $email  تفعيل البريد الإلكتروني (Email)
     * @param  int|null  $networkId  معرف الشبكة (لتطبيق سياسات وبوابات الرسائل النصية الخاصة)
     * @param  SmsMessageType|null  $smsMessageType  نوع الرسالة النصية المحدد بدقة (Enum)
     * @param  string|null  $emailSubject  عنوان البريد المخصص (يسقط على العنوان الرئيسي إذا كان فارغاً)
     * @param  string|null  $image  مسار الصورة المرفقة
     * @param  string|null  $actionUrl  رابط الإجراء أو التوجيه التفاعلي
     * @param  array  $extraData  بيانات إضافية مخصصة لحمولة الـ Push
     * @return Notification|array<int, Notification|null>|null يرجع كائن الإشعار للمستخدم المفرد، أو مصفوفة نتائج للإرسال الجماعي
     */
    public function sendDirect(
        int|array $userId,
        NotificationType|string $type,
        ?string $title,
        string $message,
        bool $inApp = true,
        bool $push = true,
        bool $sms = false,
        bool $email = false,
        ?int $networkId = null,
        ?SmsMessageType $smsMessageType = null,
        ?string $emailSubject = null,
        ?string $image = null,
        ?string $actionUrl = null,
        array $extraData = [],
        ?string $phoneNumberOverride = null
    ): Notification|array|null {
        $isSingle = ! is_array($userId);
        $ids = $isSingle ? [$userId] : array_values(array_unique(array_filter($userId)));

        if (empty($ids)) {
            return $isSingle ? null : [];
        }

        $typeEnum = $type instanceof NotificationType ? $type : (NotificationType::tryFrom((string) $type) ?? NotificationType::INFO);
        $typeValue = $typeEnum->value;

        $results = [];

        foreach ($ids as $userId) {
            $user = $this->userRepository->findById((int) $userId);
            if (! $user) {
                Log::warning("NotificationService::sendDirect: User ID {$userId} not found.");
                $results[$userId] = null;

                continue;
            }

            $notification = null;

            // 1. قناة الإشعارات الداخلية للتطبيق (In-App Database Record)
            if ($inApp) {
                $notification = $this->notificationRepository->create([
                    'user_id' => $user->user_id,
                    'notification_type' => $typeValue,
                    'notification_title' => $title ?? 'إشعار',
                    'notification_message' => $message,
                    'notification_image' => $image,
                    'is_read' => false,
                    'read_at' => null,
                ]);
            }

            // 2. قناة إشعارات الدفع الفوري (FCM Push)
            if ($push && $this->systemSettingService->getBool('firebase_enabled', true)) {
                $payloadData = array_merge([
                    'notification_id' => $notification?->notification_id,
                    'notification_type' => $typeValue,
                ], $extraData);

                if ($actionUrl) {
                    $payloadData['action_url'] = $actionUrl;
                }

                SendFcmNotificationJob::dispatch(
                    userId: $user->user_id,
                    title: $title ?? config('app.name', 'NetSales'),
                    body: $message,
                    data: $payloadData,
                    image: $image
                );
            }

            // 3. قناة الرسائل النصية القصيرة (SMS)
            $smsTargetPhone = $phoneNumberOverride ?: $user->user_phone_number;
            if ($sms && ! empty($smsTargetPhone) && $this->systemSettingService->getBool('sms_enabled', true)) {
                $isFinancialMovement = $smsMessageType && in_array($smsMessageType, [SmsMessageType::RECEIPT, SmsMessageType::PAYMENT], true);
                $smsBody = $isFinancialMovement ? $message : ((! empty($title) && $title !== $message) ? "{$title}\n{$message}" : $message);

                SendSmsNotificationJob::dispatch(
                    phoneNumbers: $smsTargetPhone,
                    message: $smsBody,
                    priority: 1,
                    recipientId: $networkId,
                    messageType: $smsMessageType ?? SmsMessageType::ADMIN_BROADCAST
                );
            }

            // 4. قناة البريد الإلكتروني (Email)
            if ($email && ! empty($user->user_email) && $this->systemSettingService->getBool('email_enabled', true)) {
                SendEmailNotificationJob::dispatch(
                    toEmail: $user->user_email,
                    subject: $emailSubject ?: $title,
                    userName: $user->user_full_name,
                    ctaLink: $actionUrl ?: config('app.url'),
                    body: $message
                );
            }

            $results[$userId] = $notification;
        }

        return $isSingle ? ($results[$ids[0]] ?? null) : $results;
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
     * إرسال إشعار لجمهور متعدد عبر قنوات مختارة بالاعتماد على المحرك الموحد sendDirect.
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
        $recipientIds = $recipients->pluck('user_id')->all();

        $this->sendDirect(
            userId: $recipientIds,
            type: $type,
            title: $title,
            message: $body,
            inApp: in_array('in_app', $channels, true),
            push: in_array('fcm', $channels, true) || in_array('push', $channels, true),
            sms: in_array('sms', $channels, true),
            email: in_array('email', $channels, true),
            networkId: null,
            smsMessageType: SmsMessageType::ADMIN_BROADCAST,
            emailSubject: $title,
            image: $imagePath,
            actionUrl: config('app.url')
        );

        return [
            'recipients_count' => count($recipientIds),
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
     * جلب المستخدمين المرتبطين بشبكة محددة ونقاط البيع التابعة لها.
     */
    protected function resolveNetworkUsers(int $networkId): Collection
    {
        $network = Network::with(['owner', 'pos.owner'])->find($networkId);
        if (! $network) {
            return new Collection;
        }

        $users = collect();
        if ($network->owner->status === UserStatus::ACTIVE->value) {
            $users->push($network->owner);
        }

        foreach ($network->pos as $pos) {
            if ($pos->owner->status === UserStatus::ACTIVE->value) {
                $users->push($pos->owner);
            }
        }

        return $users->unique('user_id');
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
}
