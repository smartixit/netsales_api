<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\OtpType;
use App\Enums\UserStatus;
use App\Http\Requests\API\V1\System\GenerateOtpRequest;
use App\Http\Requests\API\V1\System\VerifyOtpRequest;
use App\Interfaces\OtpRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\Otp;
use App\Models\User;
use App\Services\Support\SMSGateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة التحقق بالرمز المؤقت لمرة واحدة (Centralized OTP Service).
 *
 * صُممت وفق أفضل الممارسات المعمارية لمشروع مبيعات الشبكة NetSales:
 * 1. عزل تام عن طبقة الـ HTTP واستجابات JsonResponse.
 * 2. الاعتماد الكامل على مستودع OtpRepositoryInterface مع حظر الاستعلامات المباشرة.
 * 3. توليد أكواد مشفرة آمنة (Cryptographically Secure Random).
 * 4. حماية ضد هجمات التخمين والقوة الغاشمة (Brute-Force Lockout) وفترات التهدئة (Cooldown).
 * 5. التكامل مع SMSGateService لإرسال الرسائل للأرقام اليمنية والدولية.
 * 6. توثيق أمني وسجلات التدقيق عبر SystemLoggerService.
 */
class OtpService
{
    public const DEFAULT_LENGTH = 6;

    public const DEFAULT_EXPIRATION_MINUTES = 5;

    public const DEFAULT_MAX_ATTEMPTS = 3;

    public const DEFAULT_COOLDOWN_SECONDS = 60;

    public const MAX_DAILY_RESET_PASSWORD_REQUESTS = 1;

    public const MAX_DAILY_2FA_REQUESTS = 3;

    public function __construct(
        protected OtpRepositoryInterface $otpRepository,
        protected SMSGateService $smsGateService,
        protected UserRepositoryInterface $userRepository,
        protected NotificationService $notificationService,
    ) {}

    /**
     * توليد رمز OTP جديد لمستخدم محدد وإرساله عبر SMS.
     *
     * @return array{code: string, expires_at: Carbon}
     */
    public function generateOtpForUser(
        User $user,
        OtpType|string $type = OtpType::OTP_2FA,
        int $expirationMinutes = self::DEFAULT_EXPIRATION_MINUTES,
        int $length = self::DEFAULT_LENGTH
    ): array {
        $otpType = $type instanceof OtpType ? $type : (OtpType::tryFrom((string) $type) ?? OtpType::OTP_2FA);

        // 1. فحص الحد الأقصى اليومي لطلبات الرمز حسب الغرض
        if ($otpType === OtpType::RESET_PASSWORD) {
            $todayCount = $this->otpRepository->getTodayRequestsCount($user->user_id, $otpType);
            if ($todayCount >= self::MAX_DAILY_RESET_PASSWORD_REQUESTS) {
                throw ValidationException::withMessages([
                    'otp' => ['تم استنفاد الحد الأقصى لطلب استعادة كلمة المرور لهذا اليوم (مرة واحدة يومياً). يرجى المحاولة بعد 24 ساعة أو التواصل مع الدعم الفني.'],
                ]);
            }
        } elseif ($otpType === OtpType::OTP_2FA) {
            $todayCount = $this->otpRepository->getTodayRequestsCount($user->user_id, $otpType);
            if ($todayCount >= self::MAX_DAILY_2FA_REQUESTS) {
                throw ValidationException::withMessages([
                    'otp' => ['تم تجاوز الحد الأقصى المسموح به لطلب كود المصادقة الثنائية لهذا اليوم (3 مرات يومياً).'],
                ]);
            }
        }

        // 2. فحص فترة التهدئة
        $remainingCooldown = $this->otpRepository->getCooldownRemainingSeconds(
            $user->user_id,
            $otpType,
            self::DEFAULT_COOLDOWN_SECONDS
        );

        if ($remainingCooldown > 0) {
            throw ValidationException::withMessages([
                'otp' => ["يرجى الانتظار {$remainingCooldown} ثانية قبل طلب رمز جديد."],
            ]);
        }

        return DB::transaction(function () use ($user, $otpType, $expirationMinutes, $length) {
            // 1. إبطال الرموز السابقة لنفس الغرض
            $this->otpRepository->invalidateUserOtps($user->user_id, $otpType);

            // 2. توليد رمز عشوائي رقمي
            $min = (int) ('1'.str_repeat('0', $length - 1));
            $max = (int) str_repeat('9', $length);
            $plainCode = (string) random_int($min, $max);

            $expiresAt = Carbon::now('Asia/Aden')->addMinutes($expirationMinutes);

            // 3. حفظ الرمز عبر المستودع
            $this->otpRepository->createOtp(
                userId: $user->user_id,
                code: $plainCode,
                type: $otpType,
                expiresInMinutes: $expirationMinutes
            );

            // 4. إرسال رسالة SMS عبر البوابة
            $this->smsGateService->sendOtp(
                phoneNumber: $user->user_phone_number,
                code: $plainCode
            );

            // 5. تسجيل إشعار موجه للمستخدم عبر خدمة الإشعارات المركزية
            $this->notificationService->sendDirect(
                userId: (int) $user->user_id,
                type: NotificationType::INFO,
                title: "🔐 رمز التحقق (OTP)",
                message:"🔐 رمز التحقق الخاص بك هو: {$plainCode}",
            );

            // 6. توثيق أمني
            SystemLoggerService::log(
                module: 'auth',
                action: 'otp_generated',
                entityType: 'User',
                entityId: $user->user_id,
                newData: [
                    'type' => $otpType->value,
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
     * توليد رمز OTP عبر رقم الهاتف (لاستعادة كلمة المرور أو تفعيل الحساب).
     *
     * @return array{code: string, expires_at: Carbon, user: User}
     */
    public function generateOtpForPhone(
        string $phone,
        OtpType|string $type = OtpType::RESET_PASSWORD
    ): array {
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

        $result = $this->generateOtpForUser($user, $type);
        $result['user'] = $user;

        return $result;
    }

    /**
     * التحقق من صحة رمز OTP المدخل.
     */
    public function verifyOtp(
        int $userId,
        string $code,
        OtpType|string $type = OtpType::OTP_2FA,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS
    ): bool {
        $otpType = $type instanceof OtpType ? $type : (OtpType::tryFrom((string) $type) ?? OtpType::OTP_2FA);

        $otp = $this->otpRepository->getLatestValidOtp($userId, $otpType);

        if (! $otp) {
            SystemLoggerService::warning('otp_not_found', ['user_id' => $userId, 'type' => $otpType->value], $userId);

            throw ValidationException::withMessages([
                'otp' => ['لم يتم العثور على رمز تحقق صالح، يرجى طلب رمز جديد.'],
            ]);
        }

        // فحص الصلاحية وانتهاء المدة
        if ($otp->isExpired() || ! $otp->isValid()) {
            $this->otpRepository->markAsUsed($otp->otp_id);

            SystemLoggerService::warning('otp_expired', ['otp_id' => $otp->otp_id], $userId);

            throw ValidationException::withMessages([
                'otp' => ['انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.'],
            ]);
        }

        // فحص عدد المحاولات الفاشلة
        if ($otp->otp_failure_attempts >= $maxAttempts) {
            $this->otpRepository->markAsUsed($otp->otp_id);

            SystemLoggerService::warning('otp_max_attempts_exceeded', [
                'otp_id' => $otp->otp_id,
                'attempts' => $otp->otp_failure_attempts,
            ], $userId);

            throw ValidationException::withMessages([
                'otp' => ['تم تجاوز الحد الأقصى للمحاولات الخاطئة. تم إبطال الرمز لحماية حسابك.'],
            ]);
        }

        // مطابقة الكود
        if ($code === $otp->otp_code) {
            $this->otpRepository->markAsUsed($otp->otp_id);

            SystemLoggerService::logAuth('otp_verified_successfully', $userId, [
                'otp_id' => $otp->otp_id,
                'type' => $otpType->value,
            ]);

            return true;
        }

        // تسجيل المحاولة الخاطئة
        $newAttempts = $this->otpRepository->incrementFailureAttempts($otp->otp_id);
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
     * التحقق من صحة رمز OTP عبر رقم الهاتف مباشرة.
     */
    public function verifyOtpForPhone(
        string $phone,
        string $code,
        OtpType|string $type = OtpType::RESET_PASSWORD,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS
    ): bool {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_phone_number' => ['رقم الهاتف غير مسجل في النظام.'],
            ]);
        }

        return $this->verifyOtp($user->user_id, $code, $type, $maxAttempts);
    }

    /**
     * فحص إمكانية إعادة الإرسال.
     */
    public function canResend(
        int $userId,
        OtpType|string $type = OtpType::OTP_2FA,
        int $cooldownSeconds = self::DEFAULT_COOLDOWN_SECONDS
    ): bool {
        return $this->getCooldownRemainingSeconds($userId, $type, $cooldownSeconds) === 0;
    }

    /**
     * معرفة عدد الثواني المتبقية قبل إمكانية إعادة الإرسال.
     */
    public function getCooldownRemainingSeconds(
        int $userId,
        OtpType|string $type = OtpType::OTP_2FA,
        int $cooldownSeconds = self::DEFAULT_COOLDOWN_SECONDS
    ): int {
        return $this->otpRepository->getCooldownRemainingSeconds($userId, $type, $cooldownSeconds);
    }

    /**
     * معالجة طلب توليد OTP الممرر ككائن FormRequest.
     *
     * @return array{code: string, expires_at: Carbon, user: User}
     */
    public function generateOtp(GenerateOtpRequest $request): array
    {
        $phone = (string) $request->user_phone_number;
        $type = (string) ($request->otp_type ?? OtpType::RESET_PASSWORD->value);

        return $this->generateOtpForPhone($phone, $type);
    }

    /**
     * معالجة طلب التحقق من OTP الممرر ككائن FormRequest.
     */
    public function verifyOtpRequest(VerifyOtpRequest $request): bool
    {
        $phone = (string) $request->user_phone_number;
        $code = (string) $request->otp;
        $type = (string) ($request->type ?? $request->otp_type ?? OtpType::RESET_PASSWORD->value);

        return $this->verifyOtpForPhone($phone, $code, $type);
    }

    /**
     * معالجة طلب إعادة إرسال OTP الممرر ككائن FormRequest.
     *
     * @return array{code: string, expires_at: Carbon, user: User}
     */
    public function resendOtp(GenerateOtpRequest $request): array
    {
        return $this->generateOtp($request);
    }

    /**
     * إبطال جميع الرموز للمستخدم ولنوع محدد.
     */
    public function invalidateActiveOtps(int $userId, OtpType|string $type = OtpType::OTP_2FA): int
    {
        return $this->otpRepository->invalidateUserOtps($userId, $type);
    }
}
