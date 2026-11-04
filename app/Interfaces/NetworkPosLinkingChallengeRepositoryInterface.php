<?php

namespace App\Interfaces;

use App\Models\NetworkPosLinkingChallenge;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إدارة تحديات ودعوات ربط نقاط البيع بالشبكات عبر رمز QR.
 */
interface NetworkPosLinkingChallengeRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * البحث عن دعوة الربط عبر المعرف الفريد UUID.
     *
     * @param  array<int, string>  $relations
     */
    public function findByUuid(string $uuid, array $relations = []): ?NetworkPosLinkingChallenge;

    /**
     * البحث عن دعوة الربط عبر المعرف الفريد مع القفل الحصري المتشائم (Pessimistic Lock For Update).
     *
     * @param  array<int, string>  $relations
     */
    public function findByUuidForUpdate(string $uuid, array $relations = []): ?NetworkPosLinkingChallenge;

    /**
     * إنشاء تحدي ربط جديد.
     *
     * @param  array<string, mixed>  $data
     */
    public function createChallenge(array $data): NetworkPosLinkingChallenge;

    /**
     * استهلاك التحدي وإغلاقه بعد نجاح عملية الربط.
     */
    public function markAsConsumed(int $challengeId, int $consumedByUserId, int $consumedByPosId): bool;

    /**
     * إلغاء/إبطال تحدي الربط.
     */
    public function markAsRevoked(int $challengeId): bool;

    /**
     * وسم التحدي كمنتهي الصلاحية.
     */
    public function markAsExpired(int $challengeId): bool;

    /**
     * جلب الدعوات النشطة المعلقة الخاصة بشبكة معينة.
     *
     * @return Collection<int, NetworkPosLinkingChallenge>
     */
    public function getActiveForNetwork(int $networkId): Collection;
}

