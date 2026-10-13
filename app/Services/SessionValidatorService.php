<?php

namespace App\Services;

use App\Models\UserSession;
use Carbon\Carbon;

/**
 * خدمة التحقق وإدارة صلاحية الجلسات النشطة (Enterprise Session Validator Service).
 *
 * تتولى فحص وتحديث الجلسات في جدول user_sessions والتحقق من سريان مفعولها
 * دون استخدام أي توكنات عشوائية أو وسائط Bearer.
 */
class SessionValidatorService
{
    /**
     * التحقق من صلاحية معرف الجلسة واسترجاع سجل الجلسة الصالح.
     *
     * @param  string|int|null  $sessionId  معرف الجلسة المرسل في الترويسة X-Session-Id
     * @return UserSession|null سجل الجلسة الصالحة أو null إذا كانت منتهية أو ملغاة
     */
    public function validateSession(string|int|null $sessionId): ?UserSession
    {
        if (empty($sessionId)) {
            return null;
        }

        $session = UserSession::where('session_id', $sessionId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now('Asia/Aden'))
            ->first();

        if ($session) {
            // تمديد وقت آخر نشاط بصمت
            $session->update([
                'last_activity_at' => Carbon::now('Asia/Aden'),
            ]);

            return $session;
        }

        return null;
    }

    /**
     * إنهاء وإلغاء جلسة محددة فوراً.
     *
     * @param  string|int  $sessionId  معرف الجلسة المراد إنهاؤها
     */
    public function invalidateSession(string|int $sessionId): bool
    {
        $session = UserSession::where('session_id', $sessionId)->first();
        if ($session) {
            return (bool) $session->update([
                'revoked_at' => Carbon::now('Asia/Aden'),
                'expires_at' => Carbon::now('Asia/Aden'),
            ]);
        }

        return false;
    }

    /**
     * إلغاء كافة جلسات المستخدم مع إمكانية استثناء جلسة محددة.
     */
    public function invalidateAllUserSessions(int $userId, string|int|null $exceptSessionId = null): int
    {
        $query = UserSession::where('user_id', $userId)
            ->whereNull('revoked_at');

        if (! empty($exceptSessionId)) {
            $query->where('session_id', '!=', $exceptSessionId);
        }

        return $query->update([
            'revoked_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden'),
        ]);
    }
}
