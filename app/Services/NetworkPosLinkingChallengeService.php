<?php

namespace App\Services;

use App\Enums\LinkingChallengeStatus;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Interfaces\NetworkPosLinkingChallengeRepositoryInterface;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Models\NetworkPos;
use App\Models\NetworkPosLinkingChallenge;
use App\Models\Pos;
use App\Models\User;
use Carbon\Carbon;
use App\Services\Support\AsymmetricCryptoService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة تحديات ودعوات ربط نقاط البيع بالشبكات عبر رمز QR.
 *
 * تلتزم بالمعايير المعمارية الصارمة:
 * 1. تفويض كافة استعلامات البيانات لطبقة المستودعات (Repository Interfaces).
 * 2. تطبيق قواعد التحقق الأمني، التشفير اللامتناظر، والتوقيع الرقمي.
 * 3. تنفيذ العمليات المالية والحركات التغييرية ضمن معاملات قاعدة بيانات ذرية DB::transaction.
 */
class NetworkPosLinkingChallengeService
{
    public const TOKEN_PREFIX = 'NTS_LINK_V1_';

    public function __construct(
        protected AsymmetricCryptoService $cryptoService,
        protected NetworkPosService $networkPosService,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected NetworkPosRepositoryInterface $networkPosRepository,
        protected NetworkPosLinkingChallengeRepositoryInterface $challengeRepository,
        protected TenantPermissionService $tenantPermissions,
    ) {}

    /**
     * Resolve one exact POS business code for an authorized network.
     * This intentionally does not expose a public POS directory.
     */
    public function resolveLinkTarget(User $actor, int $networkId, string $posBusinessCode): Pos
    {
        try {
            $network = $this->networkRepository->findById($networkId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'target' => ['تعذر العثور على نقطة بيع مؤهلة لهذا الرمز.'],
            ]);
        }

        $network->ensureActive();

        $this->assertNetworkPermission($actor, $networkId, 'networks.create_link_challenge', 'غير مصرح لك بإدارة دعوات هذه الشبكة.');

        try {
            $pos = $this->posRepository->findByUuid(strtoupper(trim($posBusinessCode)));
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'target' => ['تعذر العثور على نقطة بيع مؤهلة لهذا الرمز.'],
            ]);
        }

        if (! $pos instanceof Pos || $pos->trashed() || ! $pos->isActive()) {
            throw ValidationException::withMessages([
                'target' => ['تعذر العثور على نقطة بيع مؤهلة لهذا الرمز.'],
            ]);
        }

        if ($this->networkPosRepository->findByNetworkAndPos($networkId, $pos->pos_id)) {
            throw ValidationException::withMessages([
                'target' => ['نقطة البيع مرتبطة بهذه الشبكة بالفعل.'],
            ]);
        }

        if ($this->challengeRepository->hasActiveForNetworkAndPos($networkId, $pos->pos_id)) {
            throw ValidationException::withMessages([
                'target' => ['توجد دعوة ربط سارية لنقطة البيع هذه بالفعل.'],
            ]);
        }

        return $pos;
    }

    /**
     * Search only eligible POS targets for an authorized Network invitation.
     *
     * @return \Illuminate\Contracts\Pagination\CursorPaginator<int, Pos>
     */
    public function searchLinkablePos(User $actor, int $networkId, string $search, int $perPage = 15, ?string $cursor = null): \Illuminate\Contracts\Pagination\CursorPaginator
    {
        try {
            $network = $this->networkRepository->findById($networkId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_id' => ['الشبكة المحددة غير موجودة.'],
            ]);
        }

        $network->ensureActive();

        $this->assertNetworkPermission($actor, $networkId, 'networks.create_link_challenge', 'غير مصرح لك بالبحث عن نقاط بيع لهذه الشبكة.');

        $results = $this->posRepository->getFiltered([
            'linkable_network_id' => $networkId,
            'linkable_search' => $search,
            'per_page' => min(max($perPage, 1), 30),
            'cursor' => $cursor,
        ], []);

        if (! $results instanceof \Illuminate\Contracts\Pagination\CursorPaginator) {
            throw new \LogicException('POS invitation search must use cursor pagination.');
        }

        return $results;
    }

    /**
     * إنشاء دعوة/تحدي ربط مشفر عبر رمز QR من جانب الشبكة.
     */
    public function createChallenge(
        User $actor,
        int $networkId,
        string $posUuid,
        int $durationMinutes = 15
    ): NetworkPosLinkingChallenge {
        try {
            $network = $this->networkRepository->findById($networkId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_id' => ['مزود الشبكة المحدد غير موجود.'],
            ]);
        }

        $network->ensureActive();

        $this->assertNetworkPermission($actor, $networkId, 'networks.create_link_challenge', 'غير مصرح لك بإنشاء دعوة ربط لهذه الشبكة.');

        return DB::transaction(function () use ($actor, $networkId, $posUuid, $durationMinutes): NetworkPosLinkingChallenge {
            $pos = $this->posRepository->findByUuidForUpdate(strtoupper(trim($posUuid)));

            if (! $pos instanceof Pos || $pos->trashed() || ! $pos->isActive()) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['نقطة البيع المستهدفة غير متاحة للربط.'],
                ]);
            }

            if ($this->networkPosRepository->findByNetworkAndPos($networkId, $pos->pos_id)) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['نقطة البيع هذه مرتبطة بالفعل بهذه الشبكة مسبقاً.'],
                ]);
            }

            if ($this->challengeRepository->hasActiveForNetworkAndPos($networkId, $pos->pos_id)) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['توجد دعوة ربط سارية لنقطة البيع هذه بالفعل.'],
                ]);
            }

            $duration = max(5, min(60, $durationMinutes));
            $expiresAt = now('Asia/Aden')->addMinutes($duration);
            $challengeUuid = Str::uuid()->toString();
            $nonce = bin2hex(random_bytes(32));

            $payloadData = [
                'v' => 1,
                'op' => 'pos_network_link',
                'cid' => $challengeUuid,
                'nid' => $networkId,
                'pid' => $pos->pos_id,
                'nonce' => $nonce,
                'exp' => $expiresAt->timestamp,
            ];

            $ciphertext = $this->cryptoService->encryptForServer(json_encode($payloadData));
            $payloadToken = self::TOKEN_PREFIX.$ciphertext;

            $challenge = $this->challengeRepository->createChallenge([
                'challenge_uuid' => $challengeUuid,
                'network_id' => $networkId,
                'pos_id' => $pos->pos_id,
                'created_by_user_id' => $actor->user_id,
                'challenge_nonce' => $nonce,
                'payload_token' => $payloadToken,
                'status' => LinkingChallengeStatus::PENDING,
                'expires_at' => $expiresAt,
            ]);

            return $challenge->load(['network', 'pos']);
        });
    }

    /**
     * معاينة وفحص دعوة الربط المشفرة من جانب نقطة البيع قبل التأكيد.
     *
     * @return array{
     *     challenge_uuid: string,
     *     network: NetworkResource,
     *     pos: PosResource,
     *     expires_at: string,
     *     is_targeted: bool
     * }
     */
    public function previewChallenge(
        User $actor,
        string $payloadToken,
        string $posUuid
    ): array {
        $payloadData = $this->decryptAndValidatePayload($payloadToken);

        $challenge = $this->challengeRepository->findByUuid(
            $payloadData['cid'],
            ['network', 'pos']
        );

        if (! $challenge || $challenge->status !== LinkingChallengeStatus::PENDING) {
            throw ValidationException::withMessages([
                'payload' => ['دعوة الربط غير صالحة أو تم استخدامها مسبقاً.'],
            ]);
        }

        if ($challenge->expires_at->isPast()) {
            $this->challengeRepository->markAsExpired($challenge->challenge_id);
            throw ValidationException::withMessages([
                'payload' => ['انتهت صلاحية دعوة الربط هذه.'],
            ]);
        }

        if (
            (int) $challenge->network_id !== $payloadData['nid']
            || $challenge->challenge_nonce !== $payloadData['nonce']
            || (int) $challenge->pos_id !== $payloadData['pid']
            || $challenge->expires_at->timestamp !== $payloadData['exp']
        ) {
            throw ValidationException::withMessages([
                'payload' => ['رمز دعوة الربط غير صالح أو تم التلاعب به.'],
            ]);
        }

        try {
            $pos = $this->posRepository->findByUuid(strtoupper(trim($posUuid)));
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'pos_uuid' => ['نقطة البيع المحددة غير موجودة.'],
            ]);
        }

        if ((int) $challenge->pos_id !== (int) $pos->pos_id) {
            throw ValidationException::withMessages([
                'pos_uuid' => ['دعوة الربط هذه مخصصة لنقطة بيع أخرى ولا يمكن ربطها بنقطة البيع الحالية.'],
            ]);
        }

        if (! $pos instanceof Pos || $pos->trashed() || ! $pos->isActive()) {
            throw ValidationException::withMessages([
                'pos_uuid' => ['نقطة البيع غير متاحة لإتمام الربط.'],
            ]);
        }

        $this->assertPosPermission($actor, (int) $pos->pos_id, 'pos.preview_link_challenge', 'غير مصرح لك بمعاينة دعوة الربط داخل نقطة البيع هذه.');

        $network = $challenge->network;
        $network->ensureActive();

        if ($this->networkPosRepository->findByNetworkAndPos($network->network_id, $pos->pos_id)) {
            throw ValidationException::withMessages([
                'pos_uuid' => ['نقطة البيع هذه مرتبطة بالفعل بهذه الشبكة مسبقاً.'],
            ]);
        }

        return [
            'challenge_uuid' => $challenge->challenge_uuid,
            'network' => new NetworkResource($network),
            'pos' => new PosResource($pos),
            'expires_at' => $challenge->expires_at->toDateTimeString(),
            'is_targeted' => true,
        ];
    }

    /**
     * تأكيد ربط نقطة البيع بالشبكة عبر التحدي المشفر (Consumer Confirmation).
     */
    public function confirmChallenge(
        User $actor,
        string $challengeUuid,
        string $posUuid
    ): NetworkPos {
        return DB::transaction(function () use ($actor, $challengeUuid, $posUuid): NetworkPos {
            $challenge = $this->challengeRepository->findByUuidForUpdate($challengeUuid);

            if (! $challenge) {
                throw ValidationException::withMessages([
                    'challenge_uuid' => ['دعوة الربط غير موجودة.'],
                ]);
            }

            if ($challenge->status !== LinkingChallengeStatus::PENDING) {
                throw ValidationException::withMessages([
                    'challenge_uuid' => ['تم استخدام دعوة الربط هذه مسبقاً أو تم إلغاؤها.'],
                ]);
            }

            if ($challenge->expires_at->isPast()) {
                $this->challengeRepository->markAsExpired($challenge->challenge_id);
                throw ValidationException::withMessages([
                    'challenge_uuid' => ['انتهت صلاحية دعوة الربط هذه.'],
                ]);
            }

            try {
                $pos = $this->posRepository->findByUuid(strtoupper(trim($posUuid)));
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['نقطة البيع المحددة غير موجودة.'],
                ]);
            }

            if ((int) $challenge->pos_id !== (int) $pos->pos_id) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['دعوة الربط هذه مخصصة لنقطة بيع أخرى.'],
                ]);
            }

            if (! $pos instanceof Pos || $pos->trashed() || ! $pos->isActive()) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['نقطة البيع غير متاحة لإتمام الربط.'],
                ]);
            }

            $this->assertPosPermission($actor, (int) $pos->pos_id, 'pos.confirm_link_challenge', 'غير مصرح لك بتأكيد دعوة الربط داخل نقطة البيع هذه.');

            try {
                $network = $this->networkRepository->findById($challenge->network_id);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'network_id' => ['الشبكة غير موجودة.'],
                ]);
            }

            $network->ensureActive();

            if ($this->networkPosRepository->findByNetworkAndPos($network->network_id, $pos->pos_id)) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['نقطة البيع مرتبطة بهذه الشبكة بالفعل.'],
                ]);
            }

            $networkPos = $this->networkPosService->create([
                'network_id' => $network->network_id,
                'pos_id' => $pos->pos_id,
                'status' => true,
            ]);

            $this->challengeRepository->markAsConsumed(
                $challenge->challenge_id,
                $actor->user_id,
                $pos->pos_id
            );

            return $networkPos;
        });
    }

    /**
     * إلغاء/إبطال دعوة الربط.
     */
    public function revokeChallenge(User $actor, string $challengeUuid): bool
    {
        return DB::transaction(function () use ($actor, $challengeUuid): bool {
            $challenge = $this->challengeRepository->findByUuidForUpdate(
                $challengeUuid,
                ['network']
            );

            if (! $challenge || ! $challenge->network) {
                return false;
            }

            $this->assertNetworkPermission(
                $actor,
                (int) $challenge->network_id,
                'networks.revoke_link_challenge',
                'غير مصرح لك بإلغاء دعوة الربط هذه.'
            );

            if ($challenge->status !== LinkingChallengeStatus::PENDING) {
                return false;
            }

            if ($challenge->expires_at->isPast()) {
                $this->challengeRepository->markAsExpired($challenge->challenge_id);
                return false;
            }

            return $this->challengeRepository->markAsRevoked($challenge->challenge_id);
        });
    }

    /**
     * Retrieve the safe status projection for a challenge owned by the actor's network.
     */
    public function getChallengeStatus(User $actor, string $challengeUuid): ?NetworkPosLinkingChallenge
    {
        $challenge = $this->challengeRepository->findByUuid($challengeUuid, ['network']);

        if (! $challenge || ! $challenge->network) {
            return null;
        }

        $canViewNetwork = $this->tenantPermissions->allowsNetwork(
            $actor,
            (int) $challenge->network_id,
            'networks.view'
        );
        $canViewPos = $this->tenantPermissions->allowsPos(
            $actor,
            (int) $challenge->pos_id,
            'networks.view'
        );

        if (! $actor->isSuperAdmin() && ! $canViewNetwork && ! $canViewPos) {
            throw ValidationException::withMessages([
                'challenge_uuid' => ['غير مصرح لك بالاطلاع على حالة دعوة الربط هذه.'],
            ]);
        }

        if (
            $challenge->status === LinkingChallengeStatus::PENDING
            && $challenge->expires_at->isPast()
        ) {
            if ($this->challengeRepository->markAsExpired($challenge->challenge_id)) {
                $challenge->status = LinkingChallengeStatus::EXPIRED;
            } else {
                // A concurrent confirmation may have consumed the challenge first.
                $challenge->refresh();
            }
        }

        return $challenge;
    }

    /**
     * فك تشفير وفحص سلامة بنية حمولة التحدي.
     *
     * @return array{
     *     v: int,
     *     op: string,
     *     cid: string,
     *     nid: int,
     *     pid: int,
     *     nonce: string,
     *     exp: int
     * }
     */
    protected function decryptAndValidatePayload(string $payloadToken): array
    {
        $trimmed = trim($payloadToken);
        if (str_starts_with($trimmed, self::TOKEN_PREFIX)) {
            $ciphertext = substr($trimmed, strlen(self::TOKEN_PREFIX));
        } else {
            $ciphertext = $trimmed;
        }

        try {
            $decryptedJson = $this->cryptoService->decryptFromServer($ciphertext);
            $data = json_decode($decryptedJson, true);
        } catch (Exception) {
            throw ValidationException::withMessages([
                'payload' => ['رمز QR غير صالح أو مشوه أو لم يصدر عن خادم NetSales.'],
            ]);
        }

        if (
            ! is_array($data)
            || ($data['v'] ?? null) !== 1
            || ($data['op'] ?? null) !== 'pos_network_link'
            || ! is_string($data['cid'] ?? null)
            || ! Str::isUuid($data['cid'])
            || ! is_int($data['nid'] ?? null)
            || $data['nid'] < 1
            || ! array_key_exists('pid', $data)
            || ! is_int($data['pid'] ?? null)
            || $data['pid'] < 1
            || ! is_string($data['nonce'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/D', $data['nonce'])
            || ! is_int($data['exp'] ?? null)
            || $data['exp'] < 1
        ) {
            throw ValidationException::withMessages([
                'payload' => ['بنية حمولة رمز QR غير مطابقة لمواصفات ربط نقاط البيع.'],
            ]);
        }

        /** @var array{v: int, op: string, cid: string, nid: int, pid: int|null, nonce: string, exp: int} $data */
        return $data;
    }
}
