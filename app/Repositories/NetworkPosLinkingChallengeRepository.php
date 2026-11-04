<?php

namespace App\Repositories;

use App\Enums\LinkingChallengeStatus;
use App\Interfaces\NetworkPosLinkingChallengeRepositoryInterface;
use App\Models\NetworkPosLinkingChallenge;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة تحديات ودعوات ربط نقاط البيع بالشبكات عبر رمز QR.
 *
 * @property NetworkPosLinkingChallenge $model
 */
class NetworkPosLinkingChallengeRepository extends BaseRepository implements NetworkPosLinkingChallengeRepositoryInterface
{
    public function __construct(NetworkPosLinkingChallenge $model)
    {
        parent::__construct($model);
    }

    /**
     * البحث عن دعوة الربط عبر المعرف الفريد UUID.
     */
    public function findByUuid(string $uuid, array $relations = []): ?NetworkPosLinkingChallenge
    {
        $query = $this->model->newQuery()->where('challenge_uuid', $uuid);

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * البحث عن دعوة الربط عبر المعرف الفريد مع القفل الحصري المتشائم (lockForUpdate).
     */
    public function findByUuidForUpdate(string $uuid, array $relations = []): ?NetworkPosLinkingChallenge
    {
        $query = $this->model->newQuery()
            ->where('challenge_uuid', $uuid)
            ->lockForUpdate();

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * إنشاء تحدي ربط جديد.
     */
    public function createChallenge(array $data): NetworkPosLinkingChallenge
    {
        /** @var NetworkPosLinkingChallenge $challenge */
        $challenge = $this->model->newQuery()->create($data);

        return $challenge;
    }

    /**
     * استهلاك التحدي وإغلاقه بعد نجاح عملية الربط.
     */
    public function markAsConsumed(int $challengeId, int $consumedByUserId, int $consumedByPosId): bool
    {
        return (bool) $this->model->newQuery()
            ->where('challenge_id', $challengeId)
            ->update([
                'status' => LinkingChallengeStatus::CONSUMED,
                'consumed_at' => now('Asia/Aden'),
                'consumed_by_user_id' => $consumedByUserId,
                'consumed_by_pos_id' => $consumedByPosId,
            ]);
    }

    /**
     * إلغاء/إبطال تحدي الربط.
     */
    public function markAsRevoked(int $challengeId): bool
    {
        return (bool) $this->model->newQuery()
            ->where('challenge_id', $challengeId)
            ->update([
                'status' => LinkingChallengeStatus::REVOKED,
            ]);
    }

    /**
     * وسم التحدي كمنتهي الصلاحية.
     */
    public function markAsExpired(int $challengeId): bool
    {
        return (bool) $this->model->newQuery()
            ->where('challenge_id', $challengeId)
            ->update([
                'status' => LinkingChallengeStatus::EXPIRED,
            ]);
    }

    /**
     * جلب الدعوات النشطة المعلقة الخاصة بشبكة معينة.
     */
    public function getActiveForNetwork(int $networkId): Collection
    {
        return $this->model->newQuery()
            ->where('network_id', $networkId)
            ->active()
            ->with(['pos'])
            ->latest('created_at')
            ->get();
    }
}
