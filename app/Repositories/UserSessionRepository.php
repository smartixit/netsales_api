<?php

namespace App\Repositories;

use App\Interfaces\UserSessionRepositoryInterface;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة جلسات المستخدمين (User Session Repository).
 *
 * يعتمد حصرياً على جدول user_sessions لإدارة جلسات الدخول والنشاط،
 * دون الاعتماد على التوكنات العشوائية أو الـ Bearer Tokens.
 *
 * @property UserSession $model
 */
class UserSessionRepository extends BaseRepository implements UserSessionRepositoryInterface
{
    public function __construct(UserSession $model)
    {
        parent::__construct($model);
    }

    /**
     * إنشاء جلسة مصادقة نشطة للمستخدم في جدول user_sessions.
     */
    public function createSession(
        int $userId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        int $durationHours = 24
    ): UserSession {
        $now = Carbon::now('Asia/Aden');
        $expiresAt = $now->copy()->addHours($durationHours);

        return $this->create([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'logged_at' => $now,
            'last_activity_at' => $now,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
        ]);
    }

    /**
     * البحث عن الجلسة النشطة الصالحة وغير المنتهية.
     */
    public function findActiveSession(int $sessionId): ?UserSession
    {
        return $this->model->newQuery()
            ->where('session_id', $sessionId)
            ->active()
            ->first();
    }

    /**
     * اسم بديل متطابق مع نتيجتي للبحث عن الجلسة النشطة.
     */
    public function findActive(int $sessionId): ?UserSession
    {
        return $this->findActiveSession($sessionId);
    }

    /**
     * إلغاء وإنهاء جلسة مستخدم محددة.
     */
    public function revokeSession(int $sessionId): bool
    {
        /** @var UserSession|null $session */
        $session = $this->model->newQuery()->lockForUpdate()->find($sessionId);
        if (! $session) {
            return false;
        }

        $session->revoke();

        return true;
    }

    /**
     * اسم بديل متطابق مع نتيجتي لإنهاء جلسة.
     */
    public function terminateSession(int $sessionId): bool
    {
        return $this->revokeSession($sessionId);
    }

    /**
     * إلغاء كافة جلسات المستخدم مع استثناء الجلسة الحالية اختيارياً.
     */
    public function revokeAllUserSessions(int $userId, ?int $exceptSessionId = null): int
    {
        $query = $this->model->newQuery()
            ->forUser($userId)
            ->whereNull('revoked_at');

        if ($exceptSessionId !== null) {
            $query->where('session_id', '!=', $exceptSessionId);
        }

        return $query->update([
            'revoked_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden'),
        ]);
    }

    /**
     * إنهاء كافة جلسات المستخدم الأخرى باستثناء الجلسة الحالية.
     */
    public function terminateAllOtherSessions(int $userId, int $currentSessionId): int
    {
        return $this->revokeAllUserSessions($userId, $currentSessionId);
    }

    /**
     * تحديث توقيت آخر نشاط للجلسة.
     */
    public function touchActivity(int $sessionId): void
    {
        /** @var UserSession|null $session */
        $session = $this->model->find($sessionId);
        if ($session) {
            $session->touchActivity();
        }
    }

    /**
     * جلب كافة الجلسات النشطة للمستخدم.
     */
    public function getActiveSessionsForUser(int $userId): Collection
    {
        return $this->model->newQuery()
            ->forUser($userId)
            ->active()
            ->orderByDesc('last_activity_at')
            ->get();
    }
}
