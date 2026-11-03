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
     * يتم حفظ هاش SHA-256 حصرياً في قاعدة البيانات، مع إبقاء الرمز الخام في الذاكرة المؤقتة $plain_token.
     */
    public function createSession(
        int $userId,
        string $deviceUuid,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $durationHours = null
    ): UserSession {
        $now = Carbon::now('Asia/Aden');
        $hours = $durationHours ?? max(1, (int) round((int) systemSetting('session_lifetime_minutes', 1440) / 60));
        $expiresAt = $now->copy()->addHours($hours);
        $rawToken = bin2hex(random_bytes(32));

        $session = new UserSession;
        $session->user_id = $userId;
        $session->device_uuid = $deviceUuid;
        $session->ip_address = $ipAddress;
        $session->user_agent = $userAgent;
        $session->logged_at = $now;
        $session->last_activity_at = $now;
        $session->expires_at = $expiresAt;
        $session->revoked_at = null;
        $session->setRawToken($rawToken);
        $session->save();

        return $session;
    }

    /**
     * البحث عن الجلسة النشطة الصالحة وغير المنتهية بالرمز العشوائي (مجزأ بـ SHA-256) أو بالمعرف الرقمي.
     */
    public function findActiveSession(string|int $sessionId): ?UserSession
    {
        return $this->model->newQuery()
            ->where(function ($q) use ($sessionId) {
                if (is_numeric($sessionId) && strlen((string) $sessionId) < 20) {
                    $q->where('session_id', (int) $sessionId);
                } else {
                    $q->where('session_token', UserSession::hashToken((string) $sessionId));
                }
            })
            ->active()
            ->first();
    }

    /**
     * البحث عن الجلسة بالرمز العشوائي (مجزأ بـ SHA-256) أو بالمعرف الرقمي (نشطة أو غير نشطة).
     */
    public function findByTokenOrId(string|int $sessionId, bool $lock = false): ?UserSession
    {
        $query = $this->model->newQuery()
            ->where(function ($q) use ($sessionId) {
                if (is_numeric($sessionId) && strlen((string) $sessionId) < 20) {
                    $q->where('session_id', (int) $sessionId);
                } else {
                    $q->where('session_token', UserSession::hashToken((string) $sessionId));
                }
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
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
        $session = $this->findByTokenOrId($sessionId, true);
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
     * إلغاء كافة الجلسات النشطة المرتبطة بمعرف جهاز محدد.
     */
    public function revokeSessionsByDeviceUuid(string $deviceUuid): int
    {
        return $this->model->newQuery()
            ->where('device_uuid', $deviceUuid)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => Carbon::now('Asia/Aden'),
                'expires_at' => Carbon::now('Asia/Aden'),
            ]);
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

    /**
     * Delete expired or revoked sessions older than cutoff.
     */
    public function deleteExpiredOrRevoked(\DateTimeInterface $cutoff): int
    {
        return $this->model->newQuery()->where(function ($query) use ($cutoff) {
            $query->where('expires_at', '<', $cutoff)
                ->orWhere('revoked_at', '<', $cutoff);
        })->delete();
    }
}
