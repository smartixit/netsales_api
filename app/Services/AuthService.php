<?php

namespace App\Services;

use App\Enums\CenterStatus;
use App\Enums\NotificationType;
use App\Enums\RoleKey;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Interfaces\CenterRepositoryInterface;
use App\Interfaces\NotificationRepositoryInterface;
use App\Interfaces\SecurityBanRepositoryInterface;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserSessionRepositoryInterface;
use App\Models\Center;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Services\Support\EmailNotificationService;
use App\Services\Support\FileUploadService;
use App\Services\Support\StorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * خدمة المصادقة وإدارة الجلسات والأجهزة (Enterprise Authentication Service).
 *
 * صُممت وفق أفضل الممارسات المعمارية لمشروع مبيعات الشبكة:
 * 1. عزل تام عن طبقة الـ HTTP واستجابات JsonResponse.
 * 2. الاعتماد الحصري على المستودعات (UserRepository, UserSessionRepository, UserDeviceRepository, SecurityBanRepository, CenterRepository).
 * 3. حظر تام لاستخدام التوكنات العشوائية؛ الجلسات تعتمد على جدول user_sessions والترويسة X-Session-Id.
 * 4. إدارة شاملة لأجهزة المستخدمين والمصادقة الثنائية (2FA).
 * 5. دورة كاملة لاستعادة وتغيير كلمات المرور والتحقق بالرمز المؤقت (OTP).
 * 6. توثيق أمني تفصيلي عبر SystemLoggerService.
 */
class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected UserSessionRepositoryInterface $userSessionRepository,
        protected UserDeviceRepositoryInterface $userDeviceRepository,
        protected SecurityBanRepositoryInterface $securityBanRepository,
        protected OtpService $otpService,
        protected FileUploadService $fileUploadService,
        protected NotificationRepositoryInterface $notificationRepository,
        protected CenterRepositoryInterface $centerRepository,
        protected StorageService $storageService,
        protected EmailNotificationService $emailNotificationService,
    ) {}

    /**
     * تسجيل الدخول للنظام والتحقق من المستخدم وحالة الحساب والأجهزة والـ 2FA.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function login(array $data): array
    {
        $user = $this->userRepository->findByPhone($data['user_phone_number']);

        if (! $user || ! Hash::check($data['user_password'], $user->user_password)) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['بيانات الاعتماد غير صحيحة.'],
            ]);
        }

        // 1. فحص حالة المستخدم
        if ($user->status === UserStatus::PENDING->value || $user->status === UserStatus::PENDING) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['حسابك قيد المراجعة والاعتماد من قبل إدارة النظام.'],
            ]);
        }

        if ($user->status === UserStatus::INACTIVE->value || $user->status === UserStatus::INACTIVE) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['تم تعطيل حسابك. يرجى التواصل مع إدارة النظام.'],
            ]);
        }

        if ($user->status === UserStatus::BANNED->value || $user->status === UserStatus::BANNED) {
            $activeBan = $this->securityBanRepository->getActiveBanForUser($user->user_id);
            $reason = $activeBan?->ban_reason ?? 'مخالفة الشروط والأحكام';

            throw ValidationException::withMessages([
                'user_phone_number' => ["تم حظر حسابك. السبب: {$reason}"],
            ]);
        }

        // فحص حالة المركز التابع له
        if ($user->center_id && $user->center) {
            if ($user->center->status === CenterStatus::PENDING->value || $user->center->status === CenterStatus::PENDING) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['مركز الخدمة التابع له قيد المراجعة والاعتماد من قبل إدارة النظام.'],
                ]);
            }

            if ($user->center->status === CenterStatus::INACTIVE->value || $user->center->status === CenterStatus::INACTIVE) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['مركز الخدمة التابع له غير مفعّل. يرجى التواصل مع الإدارة.'],
                ]);
            }
        }

        // 2. التحقق من الحظر الأمني النشط
        if ($this->securityBanRepository->isUserBanned($user->user_id)) {
            $activeBan = $this->securityBanRepository->getActiveBanForUser($user->user_id);
            $reason = $activeBan?->ban_reason ?? 'مخالفة الشروط والأحكام';

            throw ValidationException::withMessages([
                'user_phone_number' => ["تم حظر هذا الحساب من الدخول إلى النظام. السبب: {$reason}"],
            ]);
        }

        // 3. التحقق من الجهاز
        $incomingDeviceId = $data['device_uuid'] ?? null;
        if (empty($incomingDeviceId)) {
            throw ValidationException::withMessages([
                'device_uuid' => ['معرف الجهاز مطلوب لتأكيد جلسة الدخول.'],
            ]);
        }

        $existingDevice = $this->userDeviceRepository->findByUuid($user->user_id, $incomingDeviceId);

        // فحص حظر الجهاز
        if ($existingDevice && $this->securityBanRepository->isDeviceBanned($existingDevice->device_id)) {
            $activeDeviceBan = $this->securityBanRepository->getActiveBanForDevice($existingDevice->device_id);
            $reason = $activeDeviceBan?->ban_reason ?? 'حظر أمني للجهاز';

            throw ValidationException::withMessages([
                'device_uuid' => ["تم حظر هذا الجهاز من الدخول إلى النظام. السبب: {$reason}"],
            ]);
        }

        if ($existingDevice && $existingDevice->status === UserDeviceStatus::BANNED->value) {
            throw ValidationException::withMessages([
                'device_uuid' => ['تم حظر هذا الجهاز من الدخول إلى النظام.'],
            ]);
        }

        if ($existingDevice && ($existingDevice->status === UserDeviceStatus::PENDING->value || $existingDevice->status === UserDeviceStatus::PENDING)) {
            throw ValidationException::withMessages([
                'device_uuid' => ['هذا الجهاز قيد المراجعة والاعتماد من قبل إدارة النظام.'],
            ]);
        }

        // إذا كان الجهاز جديداً وميزة 2FA مفعلة للحساب
        if (! $existingDevice && $user->user_2fa_status) {
            $this->otpService->generateOtpForUser($user);

            SystemLoggerService::warning('2FA required for login from new device', ['device_uuid' => $incomingDeviceId], $user->user_id);

            return [
                'require_2fa' => true,
                'message' => 'يرجى إدخال رمز التحقق المرسل إلى هاتفك لإتمام تسجيل الدخول.',
                'user_phone_number' => $user->user_phone_number,
            ];
        }

        // تسجيل الدخول وإنشاء الجلسة عبر المعاملة
        return DB::transaction(function () use ($user, $data, $incomingDeviceId, $existingDevice) {
            // تسجيل أو تحديث الجهاز
            $device = $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: $incomingDeviceId,
                deviceName: $data['device_name'] ?? null,
                deviceType: $data['device_type'] ?? 'UNKNOWN'
            );

            // إنشاء الجلسة في user_sessions بمدة 24 ساعة فقط
            $session = $this->userSessionRepository->createSession(
                userId: $user->user_id,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                durationHours: 24
            );

            // تنبيه أمني في حال كان جهازاً ثانوياً
            if ($existingDevice && ! $existingDevice->is_primary) {
                $this->sendDeviceAlertNotification($user, $existingDevice);
            }

            SystemLoggerService::logAuth('user_logged_in', $user->user_id, [
                'session_id' => $session->session_id,
                'device_id' => $device->device_id,
            ]);

            return [
                'require_2fa' => false,
                'user' => $user->load(['role', 'center']),
                'session' => $session,
                'permissions' => $user->getAllPermissions(),
            ];
        });
    }

    /**
     * تسجيل حساب مركز ومسؤول مركز جديد (Registration Flow).
     *
     * يتم إنشاء المركز والمستخدم والجهاز بحالة PENDING انتظاراً لموافقة المدير الأعلى للنظام.
     * لا يتم إنشاء جلسة دخول في user_sessions حتى يتم الاعتماد رسمياً.
     *
     * @param  array<string, mixed>  $data
     * @param  UploadedFile|null  $centerProfilePicture  شعار المركز
     * @param  UploadedFile|null  $userProfilePicture  الصورة الشخصية للمسؤول
     * @return array<string, mixed>
     */
    public function register(
        array $data,
        ?UploadedFile $centerProfilePicture = null,
        ?UploadedFile $userProfilePicture = null
    ): array {
        $center = null;
        $user = null;

        $result = DB::transaction(function () use (
            $data,
            $centerProfilePicture,
            $userProfilePicture,
            &$center,
            &$user
        ) {
            // 1. استخراج دور موظف المركز
            $role = Role::where('role_key', RoleKey::CENTER_USER->value)->first();
            if (! $role) {
                throw new RuntimeException('دور موظف المركز غير معرف في النظام.');
            }

            // 2. إنشاء المركز مع توليد كود موحد أنيق عبر BusinessIdentifierGenerator
            $nextId = (DB::table('centers')->max('center_id') ?? 0) + 1;
            $centerCode = BusinessIdentifierGenerator::forCenter($nextId, Center::class);

            // رفع صورة/شعار المركز إن وُجد
            $centerPicturePath = null;
            if ($centerProfilePicture) {
                $centerPicturePath = $this->fileUploadService->uploadFile($centerProfilePicture, 'centers/logos');
            }

            $center = $this->centerRepository->create([
                'governorate_id' => (int) $data['governorate_id'],
                'directorate_id' => (int) $data['directorate_id'],
                'center_code' => $centerCode,
                'center_name' => $data['center_name'],
                'center_phone_number' => $data['center_phone_number'] ?? null,
                'center_address' => $data['center_address'] ?? null,
                'center_description' => $data['center_description'] ?? null,
                'center_profile_picture' => $centerPicturePath,
                'status' => CenterStatus::PENDING->value,
            ]);

            // 3. رفع الصورة الشخصية للمسؤول إن وُجدت
            $userPicturePath = null;
            $uploadedUserPic = $userProfilePicture ?? ($data['profile_picture'] ?? null);
            if ($uploadedUserPic instanceof UploadedFile) {
                $userPicturePath = $this->fileUploadService->uploadProfilePicture($uploadedUserPic, 'users');
            }

            // 4. إنشاء حساب المستخدم بحالة PENDING وربطه بالمركز والدور
            $user = $this->userRepository->create([
                'role_id' => $role->role_id,
                'center_id' => $center->center_id,
                'user_phone_number' => $data['user_phone_number'],
                'user_email' => $data['user_email'] ?? null,
                'user_password' => Hash::make($data['user_password']),
                'user_profile_picture' => $userPicturePath,
                'user_2fa_status' => false,
                'status' => UserStatus::PENDING->value,
            ]);

            // 5. تسجيل الجهاز كجهاز أساسي بحالة PENDING
            $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: $data['device_uuid'],
                deviceName: $data['device_name'] ?? 'جهاز التسجيل الأساسي',
                deviceType: $data['device_type'] ?? 'WEB',
                isPrimary: true,
                status: UserDeviceStatus::PENDING->value
            );

            // 6. توثيق العملية في سجل تدقيق النظام
            SystemLoggerService::log(
                module: 'auth',
                action: 'center_registration_requested',
                entityType: 'Center',
                entityId: $center->center_id,
                newData: [
                    'center_id' => $center->center_id,
                    'center_code' => $centerCode,
                    'center_name' => $center->center_name,
                    'user_id' => $user->user_id,
                    'user_phone_number' => $user->user_phone_number,
                    'status' => 'PENDING',
                ],
                userId: $user->user_id
            );

            return [
                'user' => $user->load(['role', 'center']),
                'center' => $center,
            ];
        });

        // 7. إرسال بريد إلكتروني يفيد باستلام الطلب وأنه قيد الفحص
        if (! empty($user->user_email)) {
            $this->emailNotificationService->sendRegistrationReceived($center, $user);
        }

        return $result;
    }

    /**
     * إتمام التحقق الثنائي وتسجيل الدخول من جهاز جديد.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function verifyTwoFactor(array $data): array
    {
        $user = $this->userRepository->findByPhone($data['user_phone_number']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف غير مسجل.'],
            ]);
        }

        // التحقق من كود الـ OTP
        $this->otpService->verifyOtp($user->user_id, $data['otp']);

        return DB::transaction(function () use ($user, $data) {
            $device = $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: $data['device_uuid'],
                deviceName: $data['device_name'] ?? null,
                deviceType: $data['device_type'] ?? 'UNKNOWN'
            );

            $session = $this->userSessionRepository->createSession(
                userId: $user->user_id,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                durationHours: 24
            );

            SystemLoggerService::logAuth('user_logged_in_2fa', $user->user_id, [
                'session_id' => $session->session_id,
                'device_id' => $device->device_id,
            ]);

            return [
                'user' => $user->load(['role', 'center']),
                'session' => $session,
                'permissions' => $user->getAllPermissions(),
            ];
        });
    }

    /**
     * جلب بيانات الملف الشخصي للمستخدم الحالي.
     *
     * @return array<string, mixed>
     */
    public function me(User $user): array
    {
        $user->load(['role', 'center', 'directPermissions']);

        return [
            'user' => $user,
            'is_super_admin' => $user->isSuperAdmin(),
            'is_center_user' => (bool) $user->center_id,
            'permissions' => $user->getAllPermissions(),
        ];
    }

    /**
     * تسجيل الخروج وإنهاء الجلسة المحددة.
     */
    public function logout(User $user, ?int $sessionId): bool
    {
        if ($sessionId) {
            $this->userSessionRepository->terminateSession($sessionId);
        }

        SystemLoggerService::logAuth('user_logged_out', $user->user_id, [
            'session_id' => $sessionId,
        ]);

        return true;
    }

    /**
     * إنهاء كافة الجلسات الأخرى على الأجهزة المختلفة للمستخدم.
     */
    public function logoutOtherDevices(User $user, int $currentSessionId): int
    {
        return $this->userSessionRepository->terminateAllOtherSessions($user->user_id, $currentSessionId);
    }

    /**
     * طلب استعادة كلمة المرور عبر إرسال كود OTP.
     */
    public function requestPasswordReset(string $phone): bool
    {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف المدخل غير مسجل في النظام.'],
            ]);
        }

        if ($user->status !== UserStatus::ACTIVE->value && $user->status !== UserStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['الحساب غير نشط، يرجى التواصل مع الإدارة.'],
            ]);
        }

        $this->otpService->generateOtpForUser($user);

        return true;
    }

    /**
     * التحقق من كود استعادة كلمة المرور.
     */
    public function verifyResetOtp(string $phone, string $otp): bool
    {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف المدخل غير مسجل.'],
            ]);
        }

        return $this->otpService->verifyOtp($user->user_id, $otp);
    }

    /**
     * اعتماد كلمة المرور الجديدة بعد استيفاء كود التحقق.
     */
    public function resetPassword(string $phone, string $otp, string $newPassword): bool
    {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف المدخل غير مسجل.'],
            ]);
        }

        // التحقق من كود الـ OTP وإبطاله
        $this->otpService->verifyOtp($user->user_id, $otp);

        return DB::transaction(function () use ($user, $newPassword) {
            $this->userRepository->update($user->user_id, [
                'user_password' => Hash::make($newPassword),
            ]);

            // إنهاء كافة الجلسات النشطة للمستخدم لحماية الحساب بعد تغيير كلمة المرور
            $this->userSessionRepository->terminateAllOtherSessions($user->user_id, -1);

            SystemLoggerService::logAuth('password_reset_completed', $user->user_id);

            return true;
        });
    }

    /**
     * تحديث كلمة المرور للمستخدم المسجل الحالي.
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (! Hash::check($currentPassword, $user->user_password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        return DB::transaction(function () use ($user, $newPassword) {
            $this->userRepository->update($user->user_id, [
                'user_password' => Hash::make($newPassword),
            ]);

            SystemLoggerService::logAuth('password_updated', $user->user_id);

            return true;
        });
    }

    /**
     * تفعيل أو تعطيل المصادقة الثنائية (2FA) للحساب.
     */
    public function toggleTwoFactor(User $user, bool $enable, ?string $otp = null): bool
    {
        if ($enable && $otp) {
            $this->otpService->verifyOtp($user->user_id, $otp);
        }

        return DB::transaction(function () use ($user, $enable) {
            $this->userRepository->update($user->user_id, [
                'user_2fa_status' => $enable,
            ]);

            SystemLoggerService::logAuth('2fa_status_changed', $user->user_id, [
                'enabled' => $enable,
            ]);

            return true;
        });
    }

    /**
     * جلب قائمة الأجهزة المقترنة بحساب المستخدم.
     */
    public function getUserDevices(User $user): Collection
    {
        return $this->userDeviceRepository->getUserDevices($user->user_id);
    }

    /**
     * إلغاء إقران جهاز محدد للمستخدم.
     */
    public function disconnectDevice(User $user, int $deviceId): bool
    {
        return $this->userDeviceRepository->disconnectDevice($deviceId, $user->user_id);
    }

    /**
     * إرسال تنبيه أمني عند تسجيل الدخول من جهاز ثانوي.
     */
    protected function sendDeviceAlertNotification(User $user, UserDevice $device): void
    {
        $deviceName = $device->device_name ?: $device->device_type;
        $ip = request()->ip() ?: 'Unknown IP';
        $time = now('Asia/Aden')->format('Y-m-d H:i:s');

        $this->notificationRepository->create([
            'user_id' => $user->user_id,
            'notification_title' => 'تنبيه أمني: دخول من جهاز إضافي',
            'notification_details' => "تم تسجيل الدخول إلى حسابك من جهاز ({$deviceName}) من العنوان ({$ip}) في ({$time}). إذا لم تكن أنت، يرجى تغيير كلمة المرور فوراً.",
            'notification_type' => NotificationType::WARNING->value,
            'is_read' => false,
        ]);
    }
}
