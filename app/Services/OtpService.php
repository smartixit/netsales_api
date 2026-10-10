<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Interfaces\NotificationRepositoryInterface;
use App\Interfaces\OtpRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Services\Support\SMSGateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * خدمة التحقق بالرمز المؤقت لمرة واحدة (Centralized OTP Service).
 *
 * صُممت وفق أفضل الممارسات المعمارية لمشروع مبيعات الشبكة:
 * 1. عزل تام عن طبقة الـ HTTP واستجابات JsonResponse.
 * 2. الاعتماد الكامل على مستودع OtpRepositoryInterface مع حظر الاستعلامات المباشرة.
 * 3. توليد أكواد مشفرة آمنة (Cryptographically Secure Pseudo-Random).
 * 4. حماية ضد هجمات التخمين والقوة الغاشمة (Brute-Force Lockout) وفترات التهدئة (Cooldown).
 * 5. التكامل مع SMSGateService لإرسال الرسائل للأرقام اليمنية والدولية.
 * 6. توثيق إشعارات النظام وسجلات التدقيق عبر SystemLoggerService.
 */
class OtpService
{
    public const DEFAULT_LENGTH = 6;
    public const DEFAULT_EXPIRATION_MINUTES = 5;
    public const DEFAULT_MAX_ATTEMPTS = 5;
    public const DEFAULT_COOLDOWN_SECONDS = 60;

    public function __construct(
        protected ?OtpRepositoryInterface $otpRepository = null,
        protected ?SMSGateService $smsGateService = null,
        protected ?UserRepositoryInterface $userRepository = null,
        protected ?NotificationRepositoryInterface $notificationRepository = null,
    ) {
        $this->otpRepository ??= app(OtpRepositoryInterface::class);
        $this->smsGateService ??= app(SMSGateService::class);
        $this->userRepository ??= app(UserRepositoryInterface::class);
        $this->notificationRepository ??= app(NotificationRepositoryInterface::class);
    }

    /**
     * توليد رمز OTP جديد لمستخدم محدد وإرساله عبر SMS.
     *
     * @return array{code: string, expires_at: Carbon}
     */
    public function generateOtpForUser(
        User $user,
        int $expirationMinutes = self::DEFAULT_EXPIRATION_MINUTES,
        int $length = self::DEFAULT_LENGTH
    ): array {
        // فحص فترة التهدئة ديناميكياً من منظومة الإعدادات
        $dynamicCooldown = (int) systemSetting('otp_cooldown_seconds', self::DEFAULT_COOLDOWN_SECONDS, $user->center_id);
        $remainingCooldown = $this->otpRepository->getCooldownRemainingSeconds($user->user_id, $dynamicCooldown);
        if ($remainingCooldown > 0) {
            throw ValidationException::withMessages([
                'otp' => ["يرجى الانتظار {$remainingCooldown} ثانية قبل طلب رمز جديد."],
            ]);
        }

        // احتساب مدة الصلاحية ديناميكياً من منظومة الإعدادات
        $effectiveMinutes = $expirationMinutes === self::DEFAULT_EXPIRATION_MINUTES
            ? (int) systemSetting('otp_expiration_minutes', self::DEFAULT_EXPIRATION_MINUTES, $user->center_id)
            : $expirationMinutes;

        return DB::transaction(function () use ($user, $effectiveMinutes, $length) {
            // 1. إبطال الرموز السابقة
            $this->otpRepository->invalidateUserOtps($user->user_id);

            // 2. توليد رمز عشوائي آمن تشفيرياً
            $min = (int) ('1'.str_repeat('0', $length - 1));
            $max = (int) str_repeat('9', $length);
            $plainCode = (string) random_int($min, $max);

            $expiresAt = Carbon::now('Asia/Aden')->addMinutes($effectiveMinutes);

            // 3. حفظ الرمز مشفراً عبر المستودع
            $this->otpRepository->createOtp(
                userId: $user->user_id,
                codeHash: Hash::make($plainCode),
                expiresAt: $expiresAt
            );

            // 4. إرسال رسالة SMS عبر البوابة
            $this->smsGateService->sendOtp(
                phoneNumber: $user->user_phone_number,
                code: $plainCode,
                centerId: $user->center_id
            );

            // 5. تسجيل إشعار في جدول الإشعارات
            $this->notificationRepository->create([
                'user_id' => $user->user_id,
                'notification_title' => 'رمز التحقق (OTP)',
                'notification_details' => "رمز التحقق الخاص بك في نظام مبيعات الشبكة هو: {$plainCode}",
                'notification_type' => NotificationType::INFO->value,
                'is_read' => false,
            ]);

            // 6. تسجيل في سجل النظام
            SystemLoggerService::log(
                module: 'auth',
                action: 'otp_generated',
                entityType: 'User',
                entityId: $user->user_id,
                newData: [
                    'length' => $length,
                    'expires_at' => $expiresAt->toDateTimeString(),
                ],
                userId: $user->user_id
            );

            return [
                'code' => $plainCode,
                'expires_at' => $expiresAt,
            ];
        });
    }

    /**
     * توليد رمز OTP عبر رقم الهاتف (لعمليات ما قبل تسجيل الدخول أو استعادة كلمة المرور).
     *
     * @return array{code: string, expires_at: Carbon, user: User}
     */
    public function generateOtpForPhone(string $phone): array
    {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف غير مسجل في النظام.'],
            ]);
        }

        if ($user->status !== UserStatus::ACTIVE->value && $user->status !== UserStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['حسابك غير نشط، يرجى التواصل مع إدارة النظام.'],
            ]);
        }

        $result = $this->generateOtpForUser($user);
        $result['user'] = $user;

        return $result;
    }

    /**
     * التحقق من صحة رمز الـ OTP المدخل من قبل المستخدم.
     */
    public function verifyOtp(int $userId, string $code, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): bool
    {
        $effectiveMaxAttempts = $maxAttempts === self::DEFAULT_MAX_ATTEMPTS
            ? (int) systemSetting('otp_max_attempts', self::DEFAULT_MAX_ATTEMPTS)
            : $maxAttempts;

        $otp = $this->otpRepository->getLatestActiveOtp($userId);

        if (! $otp) {
            SystemLoggerService::warning('otp_not_found', ['user_id' => $userId], $userId);

            throw ValidationException::withMessages([
                'otp' => ['لم يتم العثور على رمز تحقق صالح، يرجى طلب رمز جديد.'],
            ]);
        }

        // فحص انتهاء الصلاحية
        if ($otp->expires_at->isPast()) {
            $this->otpRepository->markAsUsed($otp->otp_id);

            SystemLoggerService::warning('otp_expired', ['otp_id' => $otp->otp_id], $userId);

            throw ValidationException::withMessages([
                'otp' => ['انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.'],
            ]);
        }

        // فحص الحد الأقصى للمحاولات ديناميكياً
        if ($otp->attempts >= $effectiveMaxAttempts) {
            $this->otpRepository->markAsUsed($otp->otp_id);

            SystemLoggerService::warning('otp_max_attempts_exceeded', [
                'otp_id' => $otp->otp_id,
                'attempts' => $otp->attempts,
            ], $userId);

            throw ValidationException::withMessages([
                'otp' => ['تم تجاوز الحد الأقصى للمحاولات الخاطئة. تم إبطال الرمز لحماية حسابك.'],
            ]);
        }

        // مطابقة الرمز
        if (Hash::check($code, $otp->code_hash)) {
            $this->otpRepository->markAsUsed($otp->otp_id);

            SystemLoggerService::logAuth('otp_verified_successfully', $userId, [
                'otp_id' => $otp->otp_id,
            ]);

            return true;
        }

        // احتساب المحاولة الخاطئة
        $newAttempts = $this->otpRepository->incrementAttempts($otp->otp_id);
        $remaining = max(0, $maxAttempts - $newAttempts);

        if ($newAttempts >= $maxAttempts) {
            $this->otpRepository->markAsUsed($otp->otp_id);

            throw ValidationException::withMessages([
                'otp' => ['تم تجاوز الحد الأقصى للمحاولات الخاطئة وتم إبطال الرمز.'],
            ]);
        }

        throw ValidationException::withMessages([
            'otp' => ["رمز التحقق غير صحيح. المحاولات المتبقية: {$remaining}."],
        ]);
    }

    /**
     * فحص الثواني المتبقية لإعادة الإرسال.
     */
    public function getRemainingCooldown(int $userId): int
    {
        return $this->otpRepository->getCooldownRemainingSeconds($userId, self::DEFAULT_COOLDOWN_SECONDS);
    }

    /**
     * التحقق من صحة رمز OTP عبر رقم الهاتف مباشرة.
     */
    public function verifyOtpForPhone(string $phone, string $code, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): bool
    {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف غير مسجل في النظام.'],
            ]);
        }

        return $this->verifyOtp($user->user_id, $code, $maxAttempts);
    }

    /**
     * التحقق من إمكانية إعادة إرسال رمز جديد دون مخالفة فترة التهدئة.
     */
    public function canResend(int $userId, int $cooldownSeconds = self::DEFAULT_COOLDOWN_SECONDS): bool
    {
        return $this->getCooldownRemainingSeconds($userId, $cooldownSeconds) === 0;
    }

    /**
     * عدد الثواني المتبقية قبل السماح بإعادة الإرسال.
     */
    public function getCooldownRemainingSeconds(int $userId, int $cooldownSeconds = self::DEFAULT_COOLDOWN_SECONDS): int
    {
        return $this->otpRepository->getCooldownRemainingSeconds($userId, $cooldownSeconds);
    }

    /**
     * إبطال جميع الرموز النشطة للمستخدم فورياً.
     */
    public function invalidateActiveOtps(int $userId): int
    {
        return $this->otpRepository->invalidateUserOtps($userId);
    }
}
