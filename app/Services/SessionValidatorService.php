<?php

namespace App\Services;

use App\Interfaces\UserSessionRepositoryInterface;
use App\Models\UserSession;

/**
 * خدمة التحقق وإدارة صلاحية الجلسات النشطة (Enterprise Session Validator Service).
 *
 * تتولى فحص وتحديث الجلسات في جدول user_sessions والتحقق من سريان مفعولها
 * بالاعتماد الحصري على مستودع الجلسات ودوال الموديل المعيارية.
 */
class SessionValidatorService
{
    public function __construct(
        protected UserSessionRepositoryInterface $userSessionRepository
    ) {}

    /**
     * التحقق من صلاحية معرف الجلسة واسترجاع سجل الجلسة الصالح.
     *
     * @param  string|int|null  $sessionId  معرف الجلسة المرسل في الترويسة X-Session-Id
     * @param  string  $deviceUuid  معرف الجهاز المرسل في الترويسة X-Device-Uuid
     * @return UserSession|null سجل الجلسة الصالحة أو null إذا كانت منتهية أو ملغاة
     */
    public function validateSession(string|int|null $sessionId, string $deviceUuid): ?UserSession
    {
        if (empty($sessionId) || empty($deviceUuid)) {
            return null;
        }

        $session = $this->userSessionRepository->findActiveSession($sessionId);

        if ($session && $session->isActive()) {
            // مطابقة الجهاز بدقة تامة: الجلسة المسجلة يجب أن تتطابق مع معرف الجهاز المرسل
            if (empty($session->device_uuid) || $session->device_uuid !== $deviceUuid) {
                return null;
            }

            // تمديد وقت آخر نشاط بصمت عبر دالة الموديل المساعدة
            $session->touchActivity();

            return $session;
        }

        return null;
    }

    /**
     * إنهاء وإلغاء جلسة محددة فوراً.
     *
     * @param  string|int  $sessionId  معرف أو رمز الجلسة المراد إنهاؤها
     */
    public function invalidateSession(string|int $sessionId): bool
    {
        $session = $this->userSessionRepository->findByTokenOrId($sessionId, true);

        if ($session) {
            $session->revoke();

            return true;
        }

        return false;
    }

    /**
     * إلغاء كافة جلسات المستخدم مع إمكانية استثناء جلسة محددة.
     */
    public function invalidateAllUserSessions(int $userId, string|int|null $exceptSessionId = null): int
    {
        $exceptId = is_numeric($exceptSessionId) ? (int) $exceptSessionId : null;

        return $this->userSessionRepository->revokeAllUserSessions($userId, $exceptId);
    }
}
