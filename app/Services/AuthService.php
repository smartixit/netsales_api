<?php

namespace App\Services;

use App\Enums\DeviceType;
use App\Enums\NetworkStatus;
use App\Enums\OtpType;
use App\Enums\PosStatus;
use App\Enums\ProviderSubscriptionStatus;
use App\Enums\RoleKey;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterPosRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
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
use App\Jobs\SendEmailJob;
use App\Jobs\SendSmsNotificationJob;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\Notification;
use App\Models\Pos;
use App\Models\ProviderSubscription;
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
 * صُممت وفق أفضل الممارسات المعمارية لمشروع مبيعات الشبكة NetSales:
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
    ) {}

    /**
     * تسجيل الدخول للنظام والتحقق من المستخدم وحالة الحساب والشبكة/نقطة البيع والاشتراك والأجهزة.
     */
    public function login(LoginRequest $request): array
    {
        $phoneNumber = (string) $request->user_phone_number;
        $password = (string) $request->user_password;
        $user = $this->userRepository->findByPhone($phoneNumber, true);

        if (! $user || ! Hash::check($password, $user->user_password)) {
            if ($user) {
                $this->userRepository->incrementLoginAttempts($user->user_id);
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

        if ($existingDevice && ($existingDevice->status === UserDeviceStatus::PENDING->value || $existingDevice->status === UserDeviceStatus::PENDING)) {
            throw ValidationException::withMessages([
                'device_uuid' => ['هذا الجهاز قيد المراجعة والاعتماد من قبل إدارة النظام.'],
            ]);
        }

        // 5. سيناريوهات الأجهزة والمصادقة الثنائية (OTP)
        $userDevices = $this->userDeviceRepository->getDevicesForUser($user->user_id);

        // سيناريو أ: المستخدم غير مرتبط بأي جهاز مسبقاً (جهاز أول يتطلب تأكيد OTP)
        if ($userDevices->isEmpty()) {
            $this->otpService->generateOtpForUser($user, OtpType::OTP_2FA);

            SystemLoggerService::warning('First device linking OTP sent', ['device_uuid' => $incomingDeviceId], $user->user_id);

            return [
                'require_2fa' => true,
                'is_first_device' => true,
                'message' => 'يرجى إدخال رمز التحقق المرسل إلى هاتفك لربط هذا الجهاز بحسابك لأول مرة.',
                'user_phone_number' => $user->user_phone_number,
                'device_uuid' => $incomingDeviceId,
            ];
        }

        // سيناريو ب: المستخدم مرتبط بجهاز آخر (الجهاز الحالي جديد وغير مسجل)
        if (! $existingDevice) {
            $this->otpService->generateOtpForUser($user, OtpType::OTP_2FA);

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
            $this->otpService->generateOtpForUser($user, OtpType::OTP_2FA);

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
        return DB::transaction(function () use ($user, $request, $incomingDeviceId, $existingDevice) {
            $deviceName = (string) ($request->device_name ?? $existingDevice->device_name);

            // تحديث وقت آخر استخدام للجهاز
            $this->userDeviceRepository->touchLastUsed($existingDevice->device_id);

            // إنشاء الجلسة في user_sessions بمدة 24 ساعة
            $session = $this->userSessionRepository->createSession(
                userId: $user->user_id,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                durationHours: 24
            );

            // تصفير عداد المحاولات وتحديث تاريخ الدخول
            $this->userRepository->resetLoginAttempts($user->user_id);
            $this->userRepository->updateLastLogin($user->user_id);

            // إشعار المستخدم عبر SMS إذا كان جهازاً ثانوياً
            if (! $existingDevice->is_primary) {
                SendSmsNotificationJob::dispatch(
                    $user->user_phone_number,
                    "تنبيه أمني: تم تسجيل الدخول إلى حسابك في NetSales من جهاز ثانوي ({$deviceName})."
                );
            }

            SystemLoggerService::logAuth('user_logged_in', $user->user_id, [
                'session_id' => $session->session_id,
                'device_id' => $existingDevice->device_id,
            ]);

            return [
                'require_2fa' => false,
                'user' => $user->load(['role']),
                'session' => $session,
                'permissions' => $user->getAllPermissions(),
            ];
        });
    }

    /**
     * التحقق من حالة الكيان (الشبكة أو نقطة البيع أو اشتراك الشبكة) المرتبط بحساب المستخدم.
     */
    protected function verifyUserEntityStatus(User $user): void
    {
        // 1. إذا كان المستخدم مزود شبكة
        if ($user->hasRole(RoleKey::NETWORK_ADMIN) || $user->hasRole(RoleKey::NETWORK_OWNER)) {
            $network = Network::where('network_phone_number', $user->user_phone_number)->first();

            if ($network) {
                $status = $network->status instanceof NetworkStatus ? $network->status : NetworkStatus::tryFrom((string) $network->status);

                if ($status === NetworkStatus::PENDING) {
                    throw ValidationException::withMessages([
                        'user_phone_number' => ['طلب مزود الشبكة قيد المراجعة والاعتماد من قبل إدارة النظام.'],
                    ]);
                }

                if ($status === NetworkStatus::REJECTED) {
                    $reason = $network->rejection_reason ?: 'تم رفض الطلب من قِبل إدارة النظام.';
                    throw ValidationException::withMessages([
                        'user_phone_number' => ["تم رفض طلب مزود الشبكة. السبب: {$reason}"],
                    ]);
                }

                if ($status === NetworkStatus::BANNED) {
                    throw ValidationException::withMessages([
                        'user_phone_number' => ['مزود الشبكة محظور من قِبل إدارة النظام.'],
                    ]);
                }

                if ($status === NetworkStatus::INACTIVE) {
                    throw ValidationException::withMessages([
                        'user_phone_number' => ['مزود الشبكة غير مفعّل. يرجى التواصل مع إدارة النظام.'],
                    ]);
                }

                // فحص صلاحية اشتراك الشبكة في provider_subscriptions
                $hasSubscription = ProviderSubscription::where('network_id', $network->network_id)
                    ->where('status', ProviderSubscriptionStatus::ACTIVE)
                    ->where(function ($q) {
                        $q->whereNull('subscription_end_date')
                            ->orWhere('subscription_end_date', '>', now('Asia/Aden'));
                    })
                    ->exists();

                // إذا كان لدى الشبكة اشتراكات مسجلة ولكن جميعها منتهية
                $hasAnySubscription = ProviderSubscription::where('network_id', $network->network_id)->exists();
                if ($hasAnySubscription && ! $hasSubscription) {
                    throw ValidationException::withMessages([
                        'user_phone_number' => ['اشتراك الشبكة منتهي الصلاحية، يرجى تجديد الاشتراك للمتابعة.'],
                    ]);
                }
            }
        }

        // 2. إذا كان المستخدم وكيل نقطة بيع
        if ($user->hasRole(RoleKey::POS_AGENT)) {
            $pos = Pos::where('pos_phone_number', $user->user_phone_number)->first();

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

        // التحقق من كود الـ OTP
        $this->otpService->verifyOtp($user->user_id, (string) $request->otp, OtpType::OTP_2FA);

        return DB::transaction(function () use ($user, $request) {
            $incomingDeviceId = (string) $request->device_uuid;
            $deviceName = (string) ($request->device_name ?? 'جهاز المستخدم');
            $deviceType = (string) ($request->device_type ?? 'WEB');
            $deviceTypeEnum = DeviceType::tryFrom(strtoupper($deviceType)) ?? DeviceType::UNKNOWN;

            $existingDevices = $this->userDeviceRepository->getDevicesForUser($user->user_id);
            $isFirstDevice = $existingDevices->isEmpty();

            // تسجيل الجهاز (رئيسي إذا كان الأول، وثانوي إذا كان جديداً)
            $device = $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: $incomingDeviceId,
                deviceName: $deviceName,
                deviceType: $deviceTypeEnum,
                isPrimary: $isFirstDevice,
                status: UserDeviceStatus::ACTIVE
            );

            // إنشاء الجلسة في user_sessions
            $session = $this->userSessionRepository->createSession(
                userId: $user->user_id,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
                durationHours: 24
            );

            // تصفير المحاولات وتحديث تاريخ الدخول
            $this->userRepository->resetLoginAttempts($user->user_id);
            $this->userRepository->updateLastLogin($user->user_id);

            // إشعار المستخدم برسالة SMS عند تسجيل الدخول من جهاز جديد
            $smsMessage = $isFirstDevice
                ? "تنبيه أمني: تم ربط جهازك ({$deviceName}) بحسابك في NetSales بنجاح."
                : "تنبيه أمني: تم تسجيل الدخول الى حسابك من جهاز جديد ({$deviceName}) بنجاح.";

            SendSmsNotificationJob::dispatch($user->user_phone_number, $smsMessage);

            SystemLoggerService::logAuth('user_logged_in_verified', $user->user_id, [
                'session_id' => $session->session_id,
                'device_id' => $device->device_id,
                'is_first_device' => $isFirstDevice,
            ]);

            return [
                'require_2fa' => false,
                'user' => $user->load(['role']),
                'session' => $session,
                'permissions' => $user->getAllPermissions(),
            ];
        });
    }

    /**
     * تقديم طلب تسجيل مزود شبكة جديد بحالة PENDING.
     *
     * @return array<string, mixed>
     */
    public function registerNetwork(RegisterRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            // 1. استخراج دور مدير أو مالك الشبكة
            $role = Role::whereIn('role_key', [RoleKey::NETWORK_ADMIN->value, RoleKey::NETWORK_OWNER->value])->first();
            if (! $role) {
                throw new RuntimeException('دور مدير أو مالك الشبكة غير معرف في النظام.');
            }

            // 2. رفع شعار الشبكة إن وُجد
            $networkProfileLogo = $request->file('network_profile_logo');
            $networkLogoPath = null;
            if ($networkProfileLogo) {
                $networkLogoPath = $this->fileUploadService->uploadFile($networkProfileLogo, 'networks/logos');
            }

            // 3. إنشاء سجل الشبكة بحالة PENDING عبر المستودع
            $network = $this->networkRepository->create([
                'network_name' => (string) $request->network_name,
                'network_holder_name' => (string) $request->network_holder_name,
                'governorate_id' => (int) $request->governorate_id,
                'directorate_id' => (int) $request->directorate_id,
                'network_phone_number' => (string) $request->network_phone_number,
                'network_email' => $request->network_email,
                'network_address' => $request->network_address,
                'network_profile_logo' => $networkLogoPath,
                'status' => NetworkStatus::PENDING,
            ]);

            // 4. رفع الصورة الشخصية للمسؤول إن وُجدت
            $userLogoPath = null;
            $uploadedUserPic = $request->file('user_profile_logo') ?? $request->file('profile_picture');
            if ($uploadedUserPic instanceof UploadedFile) {
                $userLogoPath = $this->fileUploadService->uploadProfilePicture($uploadedUserPic, 'users');
            }

            // 5. إنشاء حساب المستخدم بحالة PENDING عبر المستودع
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

            // 6. تسجيل الجهاز كجهاز أساسي بحالة PENDING عبر المستودع
            $deviceType = (string) ($request->device_type ?? 'WEB');
            $deviceTypeEnum = DeviceType::tryFrom(strtoupper($deviceType)) ?? DeviceType::UNKNOWN;

            $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: (string) $request->device_uuid,
                deviceName: (string) ($request->device_name ?? 'جهاز التسجيل الأساسي'),
                deviceType: $deviceTypeEnum,
                isPrimary: true,
                status: UserDeviceStatus::PENDING
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
     * اسم بديل متوافق لطلب تسجيل الشبكة.
     */
    public function register(RegisterRequest $request): array
    {
        return $this->registerNetwork($request);
    }

    /**
     * تقديم طلب تسجيل نقطة بيع جديدة بحالة PENDING.
     *
     * @return array<string, mixed>
     */
    public function registerPos(RegisterPosRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            // 1. استخراج دور وكيل نقطة البيع
            $role = Role::where('role_key', RoleKey::POS_AGENT->value)->first();
            if (! $role) {
                throw new RuntimeException('دور وكيل نقطة البيع غير معرف في النظام.');
            }

            // 2. رفع شعار نقطة البيع إن وُجد
            $posProfileLogo = $request->file('pos_profile_logo');
            $posLogoPath = null;
            if ($posProfileLogo) {
                $posLogoPath = $this->fileUploadService->uploadFile($posProfileLogo, 'pos/logos');
            }

            // 3. إنشاء سجل نقطة البيع بحالة PENDING عبر المستودع
            $pos = $this->posRepository->create([
                'pos_name' => (string) $request->pos_name,
                'pos_holder_name' => (string) $request->pos_holder_name,
                'governorate_id' => (int) $request->governorate_id,
                'directorate_id' => (int) $request->directorate_id,
                'pos_phone_number' => (string) $request->pos_phone_number,
                'pos_email' => $request->pos_email,
                'pos_address' => $request->pos_address,
                'pos_profile_logo' => $posLogoPath,
                'status' => PosStatus::PENDING,
            ]);

            // ربط النقطة بالشبكة إن حُددت
            if (! empty($request->network_id)) {
                NetworkPos::create([
                    'network_id' => (int) $request->network_id,
                    'pos_id' => $pos->pos_id,
                    'status' => false,
                ]);
            }

            // 4. رفع الصورة الشخصية للوكيل إن وُجدت
            $userLogoPath = null;
            $uploadedUserPic = $request->file('user_profile_logo') ?? $request->file('profile_picture');
            if ($uploadedUserPic instanceof UploadedFile) {
                $userLogoPath = $this->fileUploadService->uploadProfilePicture($uploadedUserPic, 'users');
            }

            // 5. إنشاء حساب المستخدم بحالة PENDING عبر المستودع
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

            // 6. تسجيل الجهاز كجهاز أساسي بحالة PENDING عبر المستودع
            $deviceType = (string) ($request->device_type ?? 'WEB');
            $deviceTypeEnum = DeviceType::tryFrom(strtoupper($deviceType)) ?? DeviceType::UNKNOWN;

            $this->userDeviceRepository->registerOrUpdateDevice(
                userId: $user->user_id,
                deviceUuid: (string) $request->device_uuid,
                deviceName: (string) ($request->device_name ?? 'جهاز التسجيل الأساسي'),
                deviceType: $deviceTypeEnum,
                isPrimary: true,
                status: UserDeviceStatus::PENDING
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
     * الموافقة على طلب مزود شبكة من قِبل مسؤول النظام وتفعيل الحساب وإرسال الإشعارات.
     */
    public function approveNetwork(int $networkId, int $adminUserId): array
    {
        return DB::transaction(function () use ($networkId, $adminUserId) {
            /** @var Network $network */
            $network = Network::lockForUpdate()->findOrFail($networkId);
            $network->update([
                'status' => NetworkStatus::ACTIVE,
                'approved_by' => $adminUserId,
                'approved_at' => now('Asia/Aden'),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            // تفعيل حساب المستخدم والجهاز الأساسي
            $user = User::where('user_phone_number', $network->network_phone_number)->first();
            if ($user) {
                $user->update(['status' => UserStatus::ACTIVE]);
                UserDevice::where('user_id', $user->user_id)->update(['status' => UserDeviceStatus::ACTIVE]);

                // تسجيل إشعار في قاعدة البيانات
                Notification::create([
                    'user_id' => $user->user_id,
                    'notification_type' => 'ACCOUNT_APPROVAL',
                    'notification_title' => 'تمت الموافقة على طلب تسجيل شبكتكم',
                    'notification_message' => "تهانينا! تمت الموافقة على طلب تسجيل شبكة {$network->network_name} وتفعيل حسابكم بنجاح. يمكنك الآن تسجيل الدخول للنظام.",
                ]);

                // إرسال رسالة نصية SMS
                SendSmsNotificationJob::dispatch(
                    $user->user_phone_number,
                    "تهانينا! تمت الموافقة على طلب تسجيل شبكة {$network->network_name} وتفعيل حسابكم بنجاح."
                );

                // إرسال بريد إلكتروني إذا وُجد
                if (! empty($user->user_email)) {
                    SendEmailJob::dispatch(
                        $user->user_email,
                        'الموافقة على طلب تسجيل الشبكة - NetSales',
                        "تهانينا! تمت مراجعة واعتماد طلب تسجيل شبكة {$network->network_name} وتفعيل حسابكم بنجاح في نظام NetSales.",
                        userName: $user->user_full_name
                    );
                }
            }

            return [
                'network' => $network,
                'user' => $user,
            ];
        });
    }

    /**
     * رفض طلب مزود شبكة من قِبل مسؤول النظام.
     */
    public function rejectNetwork(int $networkId, int $adminUserId, string $reason): array
    {
        return DB::transaction(function () use ($networkId, $adminUserId, $reason) {
            /** @var Network $network */
            $network = Network::lockForUpdate()->findOrFail($networkId);
            $network->update([
                'status' => NetworkStatus::REJECTED,
                'rejected_by' => $adminUserId,
                'rejected_at' => now('Asia/Aden'),
                'rejection_reason' => $reason,
            ]);

            $user = User::where('user_phone_number', $network->network_phone_number)->first();
            if ($user) {
                Notification::create([
                    'user_id' => $user->user_id,
                    'notification_type' => 'ACCOUNT_REJECTION',
                    'notification_title' => 'تم رفض طلب تسجيل شبكتكم',
                    'notification_message' => "نأسف لإبلاغكم بأنه تم رفض طلب تسجيل شبكة {$network->network_name}. السبب: {$reason}",
                ]);

                SendSmsNotificationJob::dispatch(
                    $user->user_phone_number,
                    "نأسف لإبلاغكم برفض طلب تسجيل شبكة {$network->network_name}. السبب: {$reason}"
                );
            }

            return [
                'network' => $network,
                'user' => $user,
            ];
        });
    }

    /**
     * الموافقة على طلب نقطة بيع من قِبل مسؤول النظام وتفعيل الحساب وإرسال الإشعارات.
     */
    public function approvePos(int $posId, int $adminUserId): array
    {
        return DB::transaction(function () use ($posId, $adminUserId) {
            /** @var Pos $pos */
            $pos = Pos::lockForUpdate()->findOrFail($posId);
            $pos->update([
                'status' => PosStatus::ACTIVE,
                'approved_by' => $adminUserId,
                'approved_at' => now('Asia/Aden'),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            // تفعيل ربط الشبكة إن وجد
            NetworkPos::where('pos_id', $pos->pos_id)->update(['status' => true]);

            $user = User::where('user_phone_number', $pos->pos_phone_number)->first();
            if ($user) {
                $user->update(['status' => UserStatus::ACTIVE]);
                UserDevice::where('user_id', $user->user_id)->update(['status' => UserDeviceStatus::ACTIVE]);

                Notification::create([
                    'user_id' => $user->user_id,
                    'notification_type' => 'ACCOUNT_APPROVAL',
                    'notification_title' => 'تمت الموافقة على طلب تسجيل نقطة البيع',
                    'notification_message' => "تهانينا! تمت الموافقة على طلب تسجيل نقطة البيع {$pos->pos_name} وتفعيل حسابكم بنجاح. يمكنك الآن تسجيل الدخول للنظام.",
                ]);

                SendSmsNotificationJob::dispatch(
                    $user->user_phone_number,
                    "تهانينا! تمت الموافقة على طلب تسجيل نقطة البيع {$pos->pos_name} وتفعيل حسابكم بنجاح."
                );

                if (! empty($user->user_email)) {
                    SendEmailJob::dispatch(
                        $user->user_email,
                        'الموافقة على طلب نقطة البيع - NetSales',
                        "تهانينا! تمت مراجعة واعتماد طلب تسجيل نقطة البيع {$pos->pos_name} وتفعيل حسابكم بنجاح.",
                        userName: $user->user_full_name
                    );
                }
            }

            return [
                'pos' => $pos,
                'user' => $user,
            ];
        });
    }

    /**
     * رفض طلب نقطة بيع من قِبل مسؤول النظام.
     */
    public function rejectPos(int $posId, int $adminUserId, string $reason): array
    {
        return DB::transaction(function () use ($posId, $adminUserId, $reason) {
            /** @var Pos $pos */
            $pos = Pos::lockForUpdate()->findOrFail($posId);
            $pos->update([
                'status' => PosStatus::REJECTED,
                'rejected_by' => $adminUserId,
                'rejected_at' => now('Asia/Aden'),
                'rejection_reason' => $reason,
            ]);

            $user = User::where('user_phone_number', $pos->pos_phone_number)->first();
            if ($user) {
                Notification::create([
                    'user_id' => $user->user_id,
                    'notification_type' => 'ACCOUNT_REJECTION',
                    'notification_title' => 'تم رفض طلب تسجيل نقطة البيع',
                    'notification_message' => "نأسف لإبلاغكم بأنه تم رفض طلب تسجيل نقطة البيع {$pos->pos_name}. السبب: {$reason}",
                ]);

                SendSmsNotificationJob::dispatch(
                    $user->user_phone_number,
                    "نأسف لإبلاغكم برفض طلب تسجيل نقطة البيع {$pos->pos_name}. السبب: {$reason}"
                );
            }

            return [
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
     * تسجيل الخروج وإنهاء الجلسة المحددة.
     */
    public function logout(User $user, ?int $sessionId): bool
    {
        if ($sessionId) {
            $this->userSessionRepository->revokeSession($sessionId);
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

        $this->otpService->generateOtpForUser($user, OtpType::RESET_PASSWORD);

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

        // 1. التحقق من كود الـ OTP وفحص محاولات الإدخال الخاطئة
        $this->otpService->verifyOtp($user->user_id, $otp, OtpType::RESET_PASSWORD);

        return DB::transaction(function () use ($user) {
            // 2. توليد كلمة مرور عشوائية مؤقتة
            $randomPassword = Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);

            // 3. تحديث كلمة المرور المشفرة للمستخدم عبر المستودع
            $this->userRepository->updatePassword($user->user_id, Hash::make($randomPassword));

            // 4. إلغاء كافة الجلسات النشطة للمستخدم لحماية الحساب
            $this->userSessionRepository->revokeAllUserSessions($user->user_id);

            // 5. إرسال كلمة المرور العشوائية وإشعار التغيير للمستخدم عبر رسالة SMS مدمجة
            $smsMessage = "تمت استعادة كلمة المرور لحسابكم في NetSales بنجاح. كلمة المرور المؤقتة الجديدة هي: {$randomPassword} - يرجى تسجيل الدخول وتغييرها فوراً.";
            SendSmsNotificationJob::dispatch(
                $user->user_phone_number,
                $smsMessage
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

        // التحقق من كود الـ OTP
        $this->otpService->verifyOtp($user->user_id, $otp, OtpType::RESET_PASSWORD);

        return DB::transaction(function () use ($user, $newPassword) {
            $finalPassword = ! empty($newPassword)
                ? $newPassword
                : Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);

            $this->userRepository->updatePassword($user->user_id, Hash::make($finalPassword));

            // إنهاء كافة الجلسات النشطة للمستخدم لحماية الحساب بعد تغيير كلمة المرور
            $this->userSessionRepository->revokeAllUserSessions($user->user_id);

            // إشعار المستخدم بأن كلمة المرور تم تغييرها عبر SMS
            $smsMessage = ! empty($newPassword)
                ? "إشعار أمني: تم تغيير كلمة المرور لحسابكم في NetSales بنجاح. إذا لم تكن أنت من قام بهذا الإجراء يرجى التواصل فوراً مع الدعم."
                : "تمت استعادة كلمة المرور لحسابكم في NetSales بنجاح. كلمة المرور المؤقتة الجديدة هي: {$finalPassword} - يرجى تسجيل الدخول وتغييرها فوراً.";

            SendSmsNotificationJob::dispatch(
                $user->user_phone_number,
                $smsMessage
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

        return DB::transaction(function () use ($user, $newPassword) {
            $this->userRepository->updatePassword($user->user_id, Hash::make($newPassword));

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
            $this->otpService->verifyOtp($user->user_id, $otp, OtpType::OTP_2FA);
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
     * إلغاء إقران جهاز محدد للمستخدم.
     */
    public function disconnectDevice(User $user, int $deviceId): bool
    {
        return $this->userDeviceRepository->disconnectDevice($deviceId, $user->user_id);
    }
}
