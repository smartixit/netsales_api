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
        ?int $durationHours = null,
        ?string $deviceUuid = null
    ): UserSession {
        $now = Carbon::now('Asia/Aden');
        $hours = $durationHours ?? max(1, (int) round((int) systemSetting('session_lifetime_minutes', 1440) / 60));
        $expiresAt = $now->copy()->addHours($hours);
        $sessionToken = bin2hex(random_bytes(32)); // 64-hex char cryptographic random token (256-bit entropy)

        return $this->create([
            'session_token' => $sessionToken,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_uuid' => $deviceUuid,
            'logged_at' => $now,
            'last_activity_at' => $now,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
        ]);
    }

    /**
     * البحث عن الجلسة النشطة الصالحة وغير المنتهية بالرمز العشوائي أو المعرف.
     */
    public function findActiveSession(string|int $sessionId): ?UserSession
    {
        return $this->model->newQuery()
            ->where(function ($q) use ($sessionId) {
                $q->where('session_token', $sessionId);
                if (is_numeric($sessionId)) {
                    $q->orWhere('session_id', (int) $sessionId);
                }
            })
            ->active()
            ->first();
    }

    /**
     * اسم بديل متطابق مع نتيجتي للبحث عن الجلسة النشطة.
     */
    public function findActive(string|int $sessionId): ?UserSession
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
