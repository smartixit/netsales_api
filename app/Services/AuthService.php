<?php

namespace App\Services;

use App\Enums\DeviceType;
use App\Enums\NetworkStatus;
use App\Enums\NotificationType;
use App\Enums\OtpType;
use App\Enums\PosStatus;
use App\Enums\RoleKey;
use App\Enums\SmsMessageType;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterNetworkRequest;
use App\Http\Requests\Api\V1\Auth\RegisterPosRequest;
use App\Http\Requests\Api\V1\Auth\RequestPasswordResetRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ToggleTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Auth\VerifyResetOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyTwoFactorRequest;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\SecurityBanRepositoryInterface;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserSessionRepositoryInterface;
use App\Models\Network;
use App\Models\Pos;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Support\EmailNotificationService;
use App\Services\Support\FileUploadService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * خدمة المصادقة وإدارة الجلسات والأجهزة (Enterprise Authentication Service).
 *
 * صُممت وفق أفضل الممارسات المعمارية لمشروع نظام مبيعات الشبكة NetSales:
 * 1. عزل تام عن طبقة الـ HTTP واستجابات JsonResponse.
 * 2. الاعتماد الحصري على المستودعات المخصصة لـ User, Session, Device, Ban, OTP, Network, POS.
 * 3. حظر تام لاستخدام التوكنات العشوائية؛ الجلسات تعتمد حصرياً على جدول user_sessions والترويسة X-Session-Id.
 * 4. إدارة شاملة لدورات تسجيل مزودي الشبكات ونقاط البيع بحالة PENDING مع نظام الموافقة والإشعار.
 * 5. إدارة أمنية مشددة للأجهزة والمصادقة الثنائية (2FA) وإشعارات الـ SMS عند الدخول من أجهزة جديدة أو ثانوية.
 * 6. دورة كاملة لاستعادة وتغيير كلمات المرور والتحقق بالرمز المؤقت (OTP).
 * 7. توثيق أمني تفصيلي عبر SystemLoggerService.
 */
class AuthService
{
    protected SystemSettingService $systemSettingService;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected UserSessionRepositoryInterface $userSessionRepository,
        protected UserDeviceRepositoryInterface $userDeviceRepository,
        protected SecurityBanRepositoryInterface $securityBanRepository,
        protected OtpService $otpService,
        protected FileUploadService $fileUploadService,
        protected EmailNotificationService $emailNotificationService,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected NotificationService $notificationService,
        ?SystemSettingService $systemSettingService = null,
    ) {
        $this->systemSettingService = $systemSettingService ?? app(SystemSettingService::class);
    }

    /**
     * أقصى عدد لمحاولات تسجيل الدخول الخاطئة.
     */
    public function getMaxLoginAttempts(): int
    {
        return $this->systemSettingService->getInt('max_login_attempts', 5);
    }

    /**
     * مدة حظر تسجيل الدخول بالدقائق.
     */
    public function getLockoutDurationMinutes(): int
    {
        return $this->systemSettingService->getInt('lockout_duration_minutes', 15);
    }

    /**
     * مدة صلاحية الجلسة بالساعات.
     */
    public function getSessionDurationHours(): int
    {
        $minutes = $this->systemSettingService->getInt('session_lifetime_minutes', 1440);

        return max(1, (int) round($minutes / 60));
    }

    /**
     * تسجيل الدخول للنظام والتحقق من المستخدم وحالة الحساب والشبكة/نقطة البيع والاشتراك والأجهزة.
     */
    public function login(LoginRequest $request): array
    {
        $phoneNumber = (string) $request->user_phone_number;
        $password = (string) $request->user_password;
        $user = $this->userRepository->findByPhone($phoneNumber, true);

        if ($user) {
            $maxAttempts = $this->getMaxLoginAttempts();
            if ($user->user_login_attempts >= $maxAttempts) {
                $lockoutMinutes = $this->getLockoutDurationMinutes();
                SystemLoggerService::warning('login_lockout_exceeded', [
                    'phone' => $phoneNumber,
                    'attempts' => $user->user_login_attempts,
                ], $user->user_id);

                throw ValidationException::withMessages([
                    'user_phone_number' => ["تم حظر تسجيل الدخول مؤقتاً لتجاوز الحد الأقصى للمحاولات الخاطئة ({$maxAttempts} محاولات). يرجى المحاولة بعد {$lockoutMinutes} دقيقة أو استعادة كلمة المرور."],
                ]);
            }
        }

        if (! $user || ! Hash::check($password, $user->getAuthPassword())) {
            if ($user) {
                $this->userRepository->incrementLoginAttempts($user->user_id);
                $remaining = max(0, $this->getMaxLoginAttempts() - ($user->user_login_attempts + 1));
                if ($remaining > 0) {
                    throw ValidationException::withMessages([
                        'user_phone_number' => ["بيانات الاعتماد غير صحيحة. المحاولات المتبقية: {$remaining}."],
                    ]);
                } else {
                    $lockoutMinutes = $this->getLockoutDurationMinutes();
                    throw ValidationException::withMessages([
                        'user_phone_number' => ["تم تجاوز الحد الأقصى للمحاولات الخاطئة. تم قفل الحساب مؤقتاً لمدة {$lockoutMinutes} دقيقة."],
                    ]);
                }
            }

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

        // 2. التحقق من الحظر الأمني النشط
        if ($this->securityBanRepository->isUserBanned($user->user_id)) {
            $activeBan = $this->securityBanRepository->getActiveBanForUser($user->user_id);
            $reason = $activeBan?->ban_reason ?? 'مخالفة الشروط والأحكام';

            throw ValidationException::withMessages([
                'user_phone_number' => ["تم حظر هذا الحساب من الدخول إلى النظام. السبب: {$reason}"],
            ]);
        }

        // 3. التحقق من حالة الكيان التابع له المستخدم (شبكة / نقطة بيع / اشتراك)
        $this->verifyUserEntityStatus($user);

        // 4. التحقق من الجهاز
        $incomingDeviceId = (string) $request->device_uuid;
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

        if ($existingDevice && ($existingDevice->status === UserDeviceStatus::BANNED->value || $existingDevice->status === UserDeviceStatus::BANNED)) {
            throw ValidationException::withMessages([
                'device_uuid' => ['تم حظر هذا الجهاز من الدخول إلى النظام.'],
            ]);
        }

        if ($existingDevice && ($existingDevice->status === UserDeviceStatus::INACTIVE->value || $existingDevice->status === UserDeviceStatus::INACTIVE)) {
            throw ValidationException::withMessages([
                'device_uuid' => ['تم تعطيل هذا الجهاز من قِبل إدارة النظام. يرجى التواصل مع الدعم الفني.'],
            ]);
        }

        if ($existingDevice && ($existingDevice->status === UserDeviceStatus::PENDING->value || $existingDevice->status === UserDeviceStatus::PENDING)) {
            throw ValidationException::withMessages([
                'device_uuid' => ['هذا الجهاز قيد المراجعة والاعتماد من قبل إدارة النظام.'],
            ]);
        }

        // 5. سيناريوهات الأجهزة والمصادقة الثنائية (OTP)
        $userDevices = $this->userDeviceRepository->getDevicesForUser($user->user_id);

        // سيناريو أ: المستخدم غير مرتبط بأي جهاز مسبقاً (جهاز أول يتطلب تأكيد OTP)
        if ($userDevices->isEmpty()) {
            $this->otpService->generateOtpForUser($user, OtpType::OTP_2FA, $incomingDeviceId);

            SystemLoggerService::warning('First device linking OTP sent', ['device_uuid' => $incomingDeviceId], $user->user_id);

            return [
                'require_2fa' => true,
                'is_primary' => true,
                'message' => 'يرجى إدخال رمز التحقق المرسل إلى هاتفك لربط هذا الجهاز بحسابك لأول مرة.',
                'user_phone_number' => $user->user_phone_number,
                'device_uuid' => $incomingDeviceId,
            ];
        }

        // سيناريو ب: المستخدم مرتبط بجهاز آخر (الجهاز الحالي جديد وغير مسجل)
        if (! $existingDevice) {
            $this->otpService->generateOtpForUser($user, OtpType::OTP_2FA, $incomingDeviceId);

            SystemLoggerService::warning('New device login attempt detected', ['device_uuid' => $incomingDeviceId], $user->user_id);

            return [
                'require_2fa' => true,
                'is_new_device' => true,
                'message' => 'تم رصد محاولة دخول من جهاز جديد. يرجى إدخال رمز التحقق المرسل إلى هاتفك.',
                'user_phone_number' => $user->user_phone_number,
                'device_uuid' => $incomingDeviceId,
            ];
        }

        // سيناريو ج: المصادقة الثنائية 2FA مفعلة وكان الجهاز ثانوي
        if ($user->user_2fa_enabled && ! $existingDevice->is_primary) {
            $this->otpService->generateOtpForUser($user, OtpType::OTP_2FA, $incomingDeviceId);

            SystemLoggerService::warning('2FA required for secondary device login', ['device_uuid' => $incomingDeviceId], $user->user_id);

            return [
                'require_2fa' => true,
                'is_new_device' => false,
                'message' => 'يرجى إدخال رمز التحقق المرسل إلى هاتفك لتأكيد الدخول من جهاز ثانوي.',
                'user_phone_number' => $user->user_phone_number,
                'device_uuid' => $incomingDeviceId,
            ];
        }

        // 6. تسجيل الدخول المباشر وإنشاء الجلسة داخل معاملة قاعدة بيانات
        return DB::transaction(function () use ($user, $request, $existingDevice) {
            $deviceName = (string) ($request->device_name ?? $existingDevice->device_name);

            // تحديث وقت آخر استخدام للجهاز وتحديث رمز الإشعارات FCM
            $this->userDeviceRepository->updateFcmToken($user->user_id, $existingDevice->device_uuid, (string) $request->fcm_token);
            $this->userDeviceRepository->touchLastUsed($existingDevice->device_id);

            // إنشاء الجلسة في user_sessions بمدة ديناميكية من إعدادات النظام
            $session = $this->userSessionRepository->createSession(
                userId: $user->user_id,
                deviceUuid: $existingDevice->device_uuid,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                durationHours: $this->getSessionDurationHours()
            );

            // تصفير عداد المحاولات وتحديث تاريخ الدخول
            $this->userRepository->resetLoginAttempts($user->user_id);
            $this->userRepository->updateLastLogin($user->user_id);

            // Alert for login from "Another" (secondary) device
            $ip = request()->ip();
            $time = now()->format('Y-m-d H:i:s');

            $body = "تنبيه أمني: دخول من جهاز موثوق\n".
            "تم تسجيل الدخول إلى حسابك من جهاز معرّف مسبقاً (جهاز ثانوي).\n".
                "تفاصيل المحاولة:\n".
                "• الجهاز: {$deviceName}\n".
                "• العنوان (IP): {$ip}\n".
                "• الوقت: {$time}";

            if (! $existingDevice->is_primary) {
                $this->notificationService->sendDirect(
                    userId: (int) $user->user_id,
                    type: NotificationType::SECURITY_LOGIN_ALERT,
                    title: 'تنبيه أمني: دخول من جهاز موثوق',
                    message: $body,
                    inApp: true,
                    push: true,
                    sms: false,
                    email: true,
                    emailSubject: 'تنبيه أمني: دخول من جهاز موثوق'
                );
            }

            SystemLoggerService::logAuth('user_logged_in', $user->user_id, [
                'session_id' => $session->session_id,
                'device_id' => $existingDevice->device_id,
            ]);

            $mustChangePassword = (bool) $user->must_change_password;

            return [
                'require_2fa' => false,
                'require_password_change' => $mustChangePassword,
                'user' => $user->load(['role']),
                'session' => $session,
                'permissions' => $mustChangePassword ? [] : $user->getAllPermissions(),
            ];
        });
    }

    /**
     * التحقق من حالة الكيان (الشبكة أو نقطة البيع أو اشتراك الشبكة) المرتبط بحساب المستخدم.
     */
    protected function verifyUserEntityStatus(User $user): void
    {
        // 1. مزود الشبكة: كل شبكة منفصلة عن الأخرى وحالة شبكة معينة لا تمنع المستخدم من تسجيل الدخول إلى حسابه.
        // يتم التحقق من حالة الشبكة وصلاحية اشتراكها عند تنفيذ العمليات على الشبكة المحددة.

        // 2. إذا كان المستخدم وكيل نقطة بيع (يمتلك نقطة بيع واحدة مرتبطة به)
        if ($user->hasRole(RoleKey::POS_AGENT)) {
            $pos = $user->pos;

            if ($pos) {
                $status = $pos->status instanceof PosStatus ? $pos->status : PosStatus::tryFrom((string) $pos->status);

                if ($status === PosStatus::PENDING) {
                    throw ValidationException::withMessages([
                        'user_phone_number' => ['طلب نقطة البيع قيد المراجعة والاعتماد من قبل إدارة النظام.'],
                    ]);
                }

                if ($status === PosStatus::REJECTED) {
                    $reason = $pos->rejection_reason ?: 'تم رفض الطلب من قِبل إدارة النظام.';
                    throw ValidationException::withMessages([
                        'user_phone_number' => ["تم رفض طلب نقطة البيع. السبب: {$reason}"],
                    ]);
                }

                if ($status === PosStatus::BANNED) {
                    throw ValidationException::withMessages([
                        'user_phone_number' => ['نقطة البيع محظورة من قِبل إدارة النظام.'],
                    ]);
                }

                if ($status === PosStatus::INACTIVE) {
                    throw ValidationException::withMessages([
                        'user_phone_number' => ['نقطة البيع غير مفعّلة. يرجى التواصل مع إدارة النظام.'],
                    ]);
                }
            }
        }
    }

    /**
     * التحقق من كود الـ OTP وإتمام الدخول للأجهزة الجديدة أو الأجهزة الأولى.
     *
     * @return array<string, mixed>
     */
    public function verifyTwoFactor(VerifyTwoFactorRequest $request): array
    {
        $user = $this->userRepository->findByPhone((string) $request->user_phone_number);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف غير مسجل.'],
            ]);
        }

        // 1. التحقق من نشاط وصلاحية المستخدم عبر دالة حراسة الحالة في الموديل
        $user->ensureActive();

        $incomingDeviceId = (string) $request->device_uuid;

        // 2. التحقق من حالة الجهاز عبر دالة حراسة الحالة في الموديل إذا كان مسجلاً مسبقاً
        $existingDevice = $this->userDeviceRepository->findByUuid($user->user_id, $incomingDeviceId);
        if ($existingDevice) {
            $existingDevice->ensureActive();
        }

        // 3. التحقق من كود الـ OTP ومطابقة معرّف الجهاز المنشئ
        $this->otpService->verifyOtp($user->user_id, (string) $request->otp, OtpType::OTP_2FA, $incomingDeviceId);

        return DB::transaction(function () use ($user, $request, $incomingDeviceId, $existingDevice) {
            $deviceName = (string) ($request->device_name ?? 'جهاز المستخدم');
            $deviceType = (string) ($request->device_type ?? 'UNKNOWN');
            $deviceTypeEnum = DeviceType::tryFrom(strtoupper($deviceType)) ?? DeviceType::UNKNOWN;

            $existingDevices = $this->userDeviceRepository->getDevicesForUser($user->user_id);
            $isFirstDevice = $existingDevices->isEmpty();
            $isNewDevice = $existingDevice === null;

            // تسجيل الجهاز (رئيسي إذا كان الأول، وثانوي إذا كان جديداً)
            $device = $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: $incomingDeviceId,
                deviceName: $deviceName,
                deviceType: $deviceTypeEnum,
                isPrimary: $isFirstDevice,
                status: UserDeviceStatus::ACTIVE,
                fcmToken: (string) $request->fcm_token
            );

            // إنشاء الجلسة في user_sessions
            $session = $this->userSessionRepository->createSession(
                userId: $user->user_id,
                deviceUuid: $incomingDeviceId,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                durationHours: $this->getSessionDurationHours()
            );

            // تصفير المحاولات وتحديث تاريخ الدخول
            $this->userRepository->resetLoginAttempts($user->user_id);
            $this->userRepository->updateLastLogin($user->user_id);

            $ip = request()->ip();
            $time = now()->format('Y-m-d H:i:s');

            // إشعار المستخدم برسالة وتنبيه أمني مخصص بحسب حالة الجهاز
            if ($isFirstDevice) {
                $smsTitle = 'تنبيه أمني: ربط جهاز أساسي';
                $smsMessage = "تنبيه أمني: تم ربط جهازك ({$deviceName}) بحسابك في NetSales بنجاح.";
            } elseif ($isNewDevice) {
                $smsTitle = 'تنبيه أمني: تسجيل دخول من جهاز جديد';
                $smsMessage = "تنبيه أمني: تم تسجيل الدخول الى حسابك من جهاز جديد\n".
                    "تفاصيل المحاولة:\n".
                    "• الجهاز: {$deviceName}\n".
                    "• العنوان (IP): {$ip}\n".
                    "• الوقت: {$time}";
            } else {
                $smsTitle = 'تنبيه أمني: تأكيد الدخول بالتحقق الثنائي';
                $smsMessage = "تنبيه أمني: تم تأكيد الدخول بالرمز المؤقت إلى حسابك من جهازك ({$deviceName}).\n".
                    "تفاصيل المحاولة:\n".
                    "• الجهاز: {$deviceName}\n".
                    "• العنوان (IP): {$ip}\n".
                    "• الوقت: {$time}";
            }

            $this->notificationService->sendDirect(
                userId: (int) $user->user_id,
                type: NotificationType::SECURITY_LOGIN_ALERT,
                title: $smsTitle,
                message: $smsMessage,
                inApp: true,
                push: true,
                sms: true,
                email: true,
                networkId: null,
                smsMessageType: SmsMessageType::ADMIN_BROADCAST,
                emailSubject: $smsTitle
            );

            SystemLoggerService::logAuth('user_logged_in_verified', $user->user_id, [
                'session_id' => $session->session_id,
                'device_id' => $device->device_id,
                'is_primary' => $isFirstDevice,
            ]);

            $mustChangePassword = (bool) $user->must_change_password;

            return [
                'require_2fa' => false,
                'require_password_change' => $mustChangePassword,
                'user' => $user->load(['role']),
                'session' => $session,
                'permissions' => $mustChangePassword ? [] : $user->getAllPermissions(),
            ];
        });
    }

    /**
     * تقديم طلب تسجيل مزود شبكة جديد بحالة PENDING.
     *
     * @return array<string, mixed>
     */
    public function registerNetwork(RegisterNetworkRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            // 1. استخراج دور مدير أو مالك الشبكة عبر النطاقات المخصصة
            $role = Role::byKey(RoleKey::NETWORK_OWNER)->active()->first();
            if (! $role) {
                throw new RuntimeException('دور مدير أو مالك الشبكة غير معرف في النظام أو غير مفعّل.');
            }

            // 2. رفع الصورة الشخصية للمسؤول إن وُجدت
            $userLogoPath = null;
            $uploadedUserPic = $request->file('user_profile_logo') ?? $request->file('profile_picture');
            if ($uploadedUserPic instanceof UploadedFile) {
                $userLogoPath = $this->fileUploadService->uploadProfilePicture($uploadedUserPic, 'users');
            }

            // 3. إنشاء حساب المستخدم أولاً بحالة PENDING عبر المستودع
            $user = $this->userRepository->create([
                'role_id' => $role->role_id,
                'user_full_name' => (string) $request->user_full_name,
                'user_phone_number' => (string) $request->user_phone_number,
                'user_email' => $request->user_email,
                'user_password' => Hash::make((string) $request->user_password),
                'user_profile_logo' => $userLogoPath,
                'user_2fa_enabled' => false,
                'user_login_attempts' => 0,
                'status' => UserStatus::PENDING,
            ]);

            // 4. رفع شعار الشبكة إن وُجد
            $networkProfileLogo = $request->file('network_profile_logo');
            $networkLogoPath = null;
            if ($networkProfileLogo) {
                $networkLogoPath = $this->fileUploadService->uploadFile($networkProfileLogo, 'networks/logos');
            }

            // 5. إنشاء سجل الشبكة بحالة PENDING مرتبطاً بمالكها
            $network = $this->networkRepository->create([
                'owner_id' => $user->user_id,
                'network_name' => (string) $request->network_name,
                'governorate_id' => (int) $request->governorate_id,
                'directorate_id' => (int) $request->directorate_id,
                'network_phone_number' => (string) $user->user_phone_number,
                'network_email' => $request->network_email,
                'network_address' => $request->network_address,
                'network_profile_logo' => $networkLogoPath,
                'status' => NetworkStatus::PENDING,
            ]);

            // 6. تسجيل الجهاز كجهاز أساسي بحالة PENDING عبر المستودع
            $deviceType = (string) ($request->device_type ?? 'UNKNOWN');
            $deviceTypeEnum = DeviceType::tryFrom(strtoupper($deviceType)) ?? DeviceType::UNKNOWN;

            $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: (string) $request->device_uuid,
                deviceName: (string) ($request->device_name ?? 'جهاز التسجيل الأساسي'),
                deviceType: $deviceTypeEnum,
                isPrimary: true,
                status: UserDeviceStatus::PENDING,
                fcmToken: (string) $request->fcm_token
            );

            SystemLoggerService::logAuth('network_registered_pending', $user->user_id, [
                'network_id' => $network->network_id,
                'network_name' => $network->network_name,
            ]);

            return [
                'success' => true,
                'message' => 'تم استلام طلبكم، سيتم مراجعة الطلب واشعاركم في حال قبول الطلب.',
                'network' => $network,
                'user' => $user,
            ];
        });
    }

    /**
     * تقديم طلب تسجيل نقطة بيع جديدة بحالة PENDING.
     *
     * @return array<string, mixed>
     */
    public function registerPos(RegisterPosRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            // 1. استخراج دور وكيل نقطة البيع عبر النطاقات المخصصة
            $role = Role::byKey(RoleKey::POS_AGENT)->active()->first();
            if (! $role) {
                throw new RuntimeException('دور وكيل نقطة البيع غير معرف في النظام أو غير مفعّل.');
            }

            // 2. رفع الصورة الشخصية للوكيل إن وُجدت
            $userLogoPath = null;
            $uploadedUserPic = $request->file('user_profile_logo') ?? $request->file('profile_picture');
            if ($uploadedUserPic instanceof UploadedFile) {
                $userLogoPath = $this->fileUploadService->uploadProfilePicture($uploadedUserPic, 'users');
            }

            // 3. إنشاء حساب المستخدم أولاً بحالة PENDING عبر المستودع
            $user = $this->userRepository->create([
                'role_id' => $role->role_id,
                'user_full_name' => (string) $request->user_full_name,
                'user_phone_number' => (string) $request->user_phone_number,
                'user_email' => $request->user_email,
                'user_password' => Hash::make((string) $request->user_password),
                'user_profile_logo' => $userLogoPath,
                'user_2fa_enabled' => false,
                'user_login_attempts' => 0,
                'status' => UserStatus::PENDING,
            ]);

            // 4. رفع شعار نقطة البيع إن وُجد
            $posProfileLogo = $request->file('pos_profile_logo');
            $posLogoPath = null;
            if ($posProfileLogo) {
                $posLogoPath = $this->fileUploadService->uploadFile($posProfileLogo, 'pos/logos');
            }

            // 5. إنشاء سجل نقطة البيع بحالة PENDING مرتبطاً بوكيلها
            $pos = $this->posRepository->create([
                'owner_id' => $user->user_id,
                'pos_name' => (string) $request->pos_name,
                'governorate_id' => (int) $request->governorate_id,
                'directorate_id' => (int) $request->directorate_id,
                'pos_phone_number' => (string) $user->user_phone_number,
                'pos_email' => $request->pos_email,
                'pos_address' => $request->pos_address,
                'pos_profile_logo' => $posLogoPath,
                'status' => PosStatus::PENDING,
            ]);

            // ربط النقطة بالشبكة إن حُددت عبر علاقة networkPos بالموديل
            if (! empty($request->network_id)) {
                $pos->networkPos()->create([
                    'network_id' => (int) $request->network_id,
                    'status' => false,
                ]);
            }

            // 6. تسجيل الجهاز كجهاز أساسي بحالة PENDING عبر المستودع
            $deviceType = (string) ($request->device_type ?? 'UNKNOWN');
            $deviceTypeEnum = DeviceType::tryFrom(strtoupper($deviceType)) ?? DeviceType::UNKNOWN;

            $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: (string) $request->device_uuid,
                deviceName: (string) ($request->device_name ?? 'جهاز التسجيل الأساسي'),
                deviceType: $deviceTypeEnum,
                isPrimary: true,
                status: UserDeviceStatus::PENDING,
                fcmToken: (string) $request->fcm_token
            );

            SystemLoggerService::logAuth('pos_registered_pending', $user->user_id, [
                'pos_id' => $pos->pos_id,
                'pos_name' => $pos->pos_name,
            ]);

            return [
                'success' => true,
                'message' => 'تم استلام طلبكم، سيتم مراجعة الطلب واشعاركم في حال قبول الطلب.',
                'pos' => $pos,
                'user' => $user,
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
        $user->load(['role']);

        return [
            'user' => $user,
            'is_super_admin' => $user->hasRole(RoleKey::SUPER_ADMIN),
            'role_key' => $user->role?->role_key,
            'permissions' => $user->getAllPermissions(),
        ];
    }

    /**
     * تسجيل الخروج وإنهاء الجلسة المحددة وتصفير رمز FCM للجهاز لضمان الخصوصية والأمان.
     */
    public function logout(User $user, ?int $sessionId, string $deviceUuid): bool
    {
        if ($sessionId) {
            $this->userSessionRepository->revokeSession($sessionId);
        }

        $this->userDeviceRepository->clearFcmTokenForDevice($user->user_id, $deviceUuid);

        SystemLoggerService::logAuth('user_logged_out', $user->user_id, [
            'session_id' => $sessionId,
            'device_uuid' => $deviceUuid,
        ]);

        return true;
    }

    /**
     * إنهاء كافة الجلسات الأخرى على الأجهزة المختلفة للمستخدم.
     */
    public function logoutOtherDevices(User $user, int $currentSessionId): int
    {
        return $this->userSessionRepository->revokeAllUserSessions($user->user_id, $currentSessionId);
    }

    /**
     * طلب استعادة كلمة المرور عبر إرسال كود OTP.
     */
    public function requestPasswordReset(RequestPasswordResetRequest|string $request): bool
    {
        $phone = $request instanceof RequestPasswordResetRequest ? (string) $request->user_phone_number : (string) $request;
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

        $deviceUuid = $request instanceof RequestPasswordResetRequest
            ? (string) ($request->device_uuid ?? request()->header('X-Device-Uuid') ?? 'RECOVERY-DEVICE')
            : 'RECOVERY-DEVICE';

        $this->otpService->generateOtpForUser($user, OtpType::RESET_PASSWORD, $deviceUuid);

        return true;
    }

    /**
     * التحقق من كود استعادة كلمة المرور وتوليد كلمة مرور عشوائية وإرسالها عبر SMS وإلغاء الجلسات.
     *
     * @return array{user: User, message: string}
     */
    public function verifyResetOtp(VerifyResetOtpRequest|string $request, ?string $otp = null): array
    {
        if ($request instanceof VerifyResetOtpRequest) {
            $phone = (string) $request->user_phone_number;
            $otp = (string) $request->otp;
        } else {
            $phone = (string) $request;
            $otp = (string) $otp;
        }

        $user = $this->userRepository->findByPhone($phone);
        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف غير مسجل في النظام.'],
            ]);
        }

        $deviceUuid = $request instanceof VerifyResetOtpRequest
            ? (string) ($request->device_uuid ?? request()->header('X-Device-Uuid') ?? 'RECOVERY-DEVICE')
            : 'RECOVERY-DEVICE';

        // 1. التحقق من كود الـ OTP وفحص محاولات الإدخال ومطابقة الجهاز
        $this->otpService->verifyOtp($user->user_id, $otp, OtpType::RESET_PASSWORD, $deviceUuid);

        return DB::transaction(function () use ($user) {
            // 2. توليد كلمة مرور عشوائية مؤقتة
            $randomPassword = Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);

            // 3. تحديث كلمة المرور المشفرة للمستخدم عبر المستودع وإلزامه بتغييرها فور الدخول
            $this->userRepository->updatePassword($user->user_id, Hash::make($randomPassword));
            $this->userRepository->setMustChangePassword($user->user_id, true);

            // 4. إلغاء كافة الجلسات النشطة للمستخدم لحماية الحساب
            $this->userSessionRepository->revokeAllUserSessions($user->user_id);

            // 5. إرسال كلمة المرور العشوائية وإشعار التغيير للمستخدم عبر رسالة SMS مدمجة
            $smsMessage = "تمت استعادة كلمة المرور لحسابكم في NetSales بنجاح. كلمة المرور المؤقتة الجديدة هي: {$randomPassword} - يرجى تسجيل الدخول وتغييرها فوراً.";
            $this->notificationService->sendDirect(
                userId: (int) $user->user_id,
                type: NotificationType::SECURITY_LOGIN_ALERT,
                title: 'تنبيه أمني: إعادة تعيين كلمة المرور',
                message: $smsMessage,
                inApp: true,
                push: true,
                sms: true,
            );

            SystemLoggerService::logAuth('password_reset_random_dispatched', $user->user_id);

            return [
                'user' => $user,
                'message' => 'تم التحقق من الرمز وتوليد كلمة مرور جديدة وإرسالها إلى هاتفك عبر رسالة SMS بنجاح.',
            ];
        });
    }

    /**
     * اعتماد كلمة المرور الجديدة بعد استيفاء كود التحقق وإلغاء الجلسات وإشعار المستخدم.
     */
    public function resetPassword(ResetPasswordRequest|string $request, ?string $otp = null, ?string $newPassword = null): bool
    {
        if ($request instanceof ResetPasswordRequest) {
            $phone = (string) $request->user_phone_number;
            $otp = (string) $request->otp;
            $newPassword = (string) ($request->password ?? $request->user_password);
        } else {
            $phone = (string) $request;
            $otp = (string) $otp;
            $newPassword = (string) $newPassword;
        }

        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف المدخل غير مسجل.'],
            ]);
        }

        $deviceUuid = (string) (request()->input('device_uuid') ?? request()->header('X-Device-Uuid') ?? 'RECOVERY-DEVICE');

        // التحقق من كود الـ OTP
        $this->otpService->verifyOtp($user->user_id, $otp, OtpType::RESET_PASSWORD, $deviceUuid);

        return DB::transaction(function () use ($user, $newPassword) {
            $finalPassword = ! empty($newPassword)
                ? $newPassword
                : Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);

            $this->userRepository->updatePassword($user->user_id, Hash::make($finalPassword));

            // إنهاء كافة الجلسات النشطة للمستخدم لحماية الحساب بعد تغيير كلمة المرور
            $this->userSessionRepository->revokeAllUserSessions($user->user_id);

            // إشعار المستخدم بأن كلمة المرور تم تغييرها عبر SMS
            $smsMessage = ! empty($newPassword)
                ? 'إشعار أمني: تم تغيير كلمة المرور لحسابكم في NetSales بنجاح. إذا لم تكن أنت من قام بهذا الإجراء يرجى التواصل فوراً مع الدعم.'
                : "تمت استعادة كلمة المرور لحسابكم في NetSales بنجاح. كلمة المرور المؤقتة الجديدة هي: {$finalPassword} - يرجى تسجيل الدخول وتغييرها فوراً.";

            $this->notificationService->sendDirect(
                userId: (int) $user->user_id,
                type: NotificationType::SECURITY_PASSWORD_CHANGED,
                title: 'إشعار أمني: تم تغيير كلمة المرور',
                message: $smsMessage,
                inApp: true,
                push: true,
                sms: true,
                email: true,
                networkId: null,
                smsMessageType: SmsMessageType::ADMIN_BROADCAST,
                emailSubject: 'إشعار أمني: تم تغيير كلمة المرور لحسابكم'
            );

            SystemLoggerService::logAuth('password_reset_completed', $user->user_id);

            return true;
        });
    }

    /**
     * تحديث كلمة المرور للمستخدم المسجل الحالي.
     */
    public function updatePassword(User $user, UpdatePasswordRequest|string $request, ?string $newPassword = null): bool
    {
        if ($request instanceof UpdatePasswordRequest) {
            $currentPassword = (string) $request->current_password;
            $newPassword = (string) $request->new_password;
        } else {
            $currentPassword = (string) $request;
            $newPassword = (string) $newPassword;
        }

        if (! Hash::check($currentPassword, $user->user_password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        if (Hash::check($newPassword, $user->user_password)) {
            throw ValidationException::withMessages([
                'new_password' => ['يجب أن تكون كلمة المرور الجديدة مختلفة عن كلمة المرور الحالية/المؤقتة.'],
            ]);
        }

        return DB::transaction(function () use ($user, $newPassword) {
            $this->userRepository->updatePassword($user->user_id, Hash::make($newPassword));
            $this->userRepository->setMustChangePassword($user->user_id, false);

            SystemLoggerService::logAuth('password_updated', $user->user_id);

            return true;
        });
    }

    /**
     * تفعيل أو تعطيل المصادقة الثنائية (2FA) للحساب.
     */
    public function toggleTwoFactor(User $user, ToggleTwoFactorRequest|bool $request, ?string $otp = null): bool
    {
        if ($request instanceof ToggleTwoFactorRequest) {
            $enable = (bool) $request->enable;
            $otp = $request->otp ? (string) $request->otp : null;
        } else {
            $enable = (bool) $request;
        }

        if ($enable && $otp) {
            $deviceUuid = (string) (request()->header('X-Device-Uuid') ?? request()->input('device_uuid') ?? 'CURRENT-DEVICE');
            $this->otpService->verifyOtp($user->user_id, $otp, OtpType::OTP_2FA, $deviceUuid);
        }

        return DB::transaction(function () use ($user, $enable) {
            $this->userRepository->setTwoFactor($user->user_id, $enable);

            SystemLoggerService::logAuth('2fa_status_changed', $user->user_id, [
                'enabled' => $enable,
            ]);

            return true;
        });
    }

    /**
     * جلب قائمة الأجهزة المقترنة بحساب المستخدم.
     *
     * @return Collection<int, UserDevice>
     */
    public function getUserDevices(User $user): Collection
    {
        return $this->userDeviceRepository->getDevicesForUser($user->user_id);
    }

    /**
     * إلغاء إقران جهاز محدد للمستخدم وتصفير رمزه وإلغاء جلساته النشطة.
     */
    public function disconnectDevice(User $user, int $deviceId): bool
    {
        $device = $this->userDeviceRepository->findById($deviceId);
        if (! $device || (int) $device->user_id !== (int) $user->user_id) {
            return false;
        }

        if (! empty($device->device_uuid)) {
            $this->userSessionRepository->revokeSessionsByDeviceUuid($device->device_uuid);
        }

        return $this->userDeviceRepository->disconnectDevice($deviceId, $user->user_id);
    }
}
