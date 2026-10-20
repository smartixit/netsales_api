<?php

namespace App\Services;

use App\Enums\BanType;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Http\Requests\API\V1\System\StoreSecurityBanRequest;
use App\Interfaces\SecurityBanRepositoryInterface;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserSessionRepositoryInterface;
use App\Models\SecurityBan;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Services\Support\AsymmetricCryptoService;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة الأمان المؤسسي وإدارة الحظر والجلسات (Enterprise Security Service).
 *
 * تتولى تطبيق القواعد والسياسات الأمنية:
 * 1. فرض ورفع الحظر الأمني للمستخدمين والأجهزة وإبطال الجلسات تلقائياً.
 * 2. إدارة الجلسات النشطة والأجهزة المرتبطة بحسابات المستخدمين.
 * 3. توفير بيانات المفاتيح العامة للتشفير غير المتماثل (Ed25519 / X25519).
 * 4. التوثيق الأمني الصارم لكافة العمليات الحساسة عبر سجل النظام.
 */
class SecurityService
{
    public function __construct(
        protected SecurityBanRepositoryInterface $securityBanRepository,
        protected UserRepositoryInterface $userRepository,
        protected UserDeviceRepositoryInterface $userDeviceRepository,
        protected UserSessionRepositoryInterface $userSessionRepository,
        protected SessionValidatorService $sessionValidatorService,
        protected AsymmetricCryptoService $asymmetricCryptoService,
    ) {}

    /**
     * جلب بيانات المفاتيح العامة السيادية للخادم.
     *
     * @return array<string, mixed>
     */
    public function getPublicKeys(): array
    {
        return $this->asymmetricCryptoService->getPublicKeysMetadata();
    }

    /**
     * استرجاع الجلسات النشطة لمستخدم محدد.
     *
     * @return Collection<int, UserSession>
     */
    public function getUserSessions(int $userId): Collection
    {
        $this->ensureUserExists($userId);

        return $this->userSessionRepository->getActiveSessionsForUser($userId);
    }

    /**
     * استرجاع الأجهزة المرتبطة بمستخدم محدد.
     *
     * @return Collection<int, UserDevice>
     */
    public function getUserDevices(int $userId): Collection
    {
        $this->ensureUserExists($userId);

        return $this->userDeviceRepository->getDevicesForUser($userId);
    }

    /**
     * تطبيق حظر أمني على مستخدم مع إبطال كافة جلساته المفتوحة فوراً.
     */
    public function banUser(StoreSecurityBanRequest $request, ?int $adminUserId = null): SecurityBan
    {
        $userId = (int) $request->user_id;
        $this->ensureUserExists($userId);

        $banType = $request->ban_type;
        $reason = (string) ($request->ban_reason ?? 'حظر أمني إداري');
        $endAt = $request->end_at ? new DateTime($request->end_at) : null;
        $adminUserId = $adminUserId ?? (auth()->id() ? (int) auth()->id() : 0);

        return DB::transaction(function () use ($userId, $adminUserId, $banType, $reason, $endAt) {
            // 1. تسجيل سجل الحظر
            $securityBan = $this->securityBanRepository->banUser(
                userId: $userId,
                bannedBy: $adminUserId,
                banType: $banType,
                banReason: $reason,
                endAt: $endAt
            );

            // 2. تحديث حالة المستخدم إلى BANNED
            $this->userRepository->setStatus($userId, UserStatus::BANNED);

            // 3. إبطال كافة الجلسات النشطة فوراً
            $this->sessionValidatorService->invalidateAllUserSessions($userId);

            // 4. توثيق العملية أمنياً
            SystemLoggerService::log(
                module: 'security',
                action: 'user_banned',
                entityType: 'User',
                entityId: $userId,
                newData: [
                    'ban_id' => $securityBan->ban_id,
                    'ban_type' => $banType instanceof BanType ? $banType->value : $banType,
                    'ban_reason' => $reason,
                    'end_at' => $endAt?->format('Y-m-d H:i:s'),
                ],
                userId: $adminUserId
            );

            return $securityBan;
        });
    }

    /**
     * تطبيق حظر أمني على جهاز محدد.
     */
    public function banDevice(StoreSecurityBanRequest $request, ?int $adminUserId = null): SecurityBan
    {
        $deviceId = (int) $request->device_id;
        $this->ensureDeviceExists($deviceId);

        $banType = $request->ban_type;
        $reason = (string) ($request->ban_reason ?? 'حظر أمني للجهاز');
        $endAt = $request->end_at ? new DateTime($request->end_at) : null;
        $adminUserId = $adminUserId ?? (auth()->id() ? (int) auth()->id() : 0);

        return DB::transaction(function () use ($deviceId, $adminUserId, $reason, $endAt) {
            // 1. تسجيل الحظر
            $securityBan = $this->securityBanRepository->banDevice(
                deviceId: $deviceId,
                bannedBy: $adminUserId,
                banReason: $reason,
                endAt: $endAt
            );

            // 2. تحديث حالة الجهاز إلى BANNED
            $this->userDeviceRepository->setDeviceStatus($deviceId, UserDeviceStatus::BANNED);

            // 3. تصفير رمز FCM وإبطال كافة الجلسات النشطة المرتبطة بهذا الجهاز فورياً
            $device = $this->userDeviceRepository->findById($deviceId);
            if ($device) {
                $device->clearFcmToken();
                if (! empty($device->device_uuid)) {
                    $this->userSessionRepository->revokeSessionsByDeviceUuid($device->device_uuid);
                }
            }

            // 4. توثيق أمني
            SystemLoggerService::log(
                module: 'security',
                action: 'device_banned',
                entityType: 'UserDevice',
                entityId: $deviceId,
                newData: [
                    'ban_id' => $securityBan->ban_id,
                    'ban_reason' => $reason,
                ],
                userId: $adminUserId
            );

            return $securityBan;
        });
    }

    /**
     * رفع الحظر الأمني واستعادة الحالة الطبيعية للمستخدم أو الجهاز.
     */
    public function unban(int $banId, ?int $adminUserId = null): bool
    {
        try {
            $ban = $this->securityBanRepository->findById($banId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'ban_id' => ['سجل الحظر الأمني غير موجود في النظام.'],
            ]);
        }

        $adminUserId = $adminUserId ?? (auth()->id() ? (int) auth()->id() : 0);

        return DB::transaction(function () use ($ban, $banId, $adminUserId) {
            // 1. رفع الحظر عبر المستودع
            $this->securityBanRepository->liftBan($banId);

            // 2. استعادة حالة المستخدم إذا كان محظوراً
            if ($ban->user_id) {
                $this->userRepository->setStatus($ban->user_id, UserStatus::ACTIVE);
            }

            // 3. استعادة حالة الجهاز إذا كان محظوراً
            if ($ban->device_id) {
                $this->userDeviceRepository->setDeviceStatus($ban->device_id, UserDeviceStatus::ACTIVE);
            }

            // 4. توثيق أمني
            SystemLoggerService::log(
                module: 'security',
                action: 'ban_lifted',
                entityType: 'SecurityBan',
                entityId: $banId,
                newData: [
                    'user_id' => $ban->user_id,
                    'device_id' => $ban->device_id,
                ],
                userId: $adminUserId
            );

            return true;
        });
    }

    /**
     * إنهاء جلسة محددة عبر معرفها.
     */
    public function terminateSession(int $sessionId, ?int $adminUserId = null): bool
    {
        try {
            $session = $this->userSessionRepository->findById($sessionId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'session_id' => ['الجلسة غير موجودة في النظام.'],
            ]);
        }

        $adminUserId = $adminUserId ?? (auth()->id() ? (int) auth()->id() : 0);

        $this->sessionValidatorService->invalidateSession($sessionId);

        SystemLoggerService::logAuth('session_terminated_manually', $session->user_id, [
            'session_id' => $sessionId,
            'terminated_by' => $adminUserId,
        ]);

        return true;
    }

    /**
     * التحقق من وجود المستخدم أو رمي استثناء التحقق.
     */
    protected function ensureUserExists(int $userId): void
    {
        try {
            $this->userRepository->findById($userId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'user_id' => ['المستخدم غير موجود في النظام.'],
            ]);
        }
    }

    /**
     * التحقق من وجود الجهاز أو رمي استثناء التحقق.
     */
    protected function ensureDeviceExists(int $deviceId): void
    {
        try {
            $this->userDeviceRepository->findById($deviceId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'device_id' => ['الجهاز غير موجود في النظام.'],
            ]);
        }
    }
}
