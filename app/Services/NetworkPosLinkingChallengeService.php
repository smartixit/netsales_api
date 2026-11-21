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

        if (! $actor->isSuperAdmin()) {
            $isOwner = (int) $network->owner_id === (int) $actor->user_id;
            $isActiveMember = $actor->networkUsers()
                ->where('network_id', $networkId)
                ->where('status', 'ACTIVE')
                ->exists();

            if (! $isOwner && ! $isActiveMember) {
                throw ValidationException::withMessages([
                    'network_id' => ['غير مصرح لك بإدارة دعوات هذه الشبكة.'],
                ]);
            }
        }

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

        return $pos->load(['governorate', 'directorate']);
    }

    /**
     * إنشاء دعوة/تحدي ربط مشفر عبر رمز QR من جانب الشبكة.
     */
    public function createChallenge(
        User $actor,
        int $networkId,
        ?int $posId = null,
        ?string $posUuid = null,
        int $durationMinutes = 15
    ): NetworkPosLinkingChallenge {
        // 1. التحقق من وجود ونشاط الشبكة عبر المستودع
        try {
            $network = $this->networkRepository->findById($networkId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_id' => ['مزود الشبكة المحدد غير موجود.'],
            ]);
        }
        $network->ensureActive();

        // 2. التحقق من صلاحية المستخدم على الشبكة
        if (! $actor->isSuperAdmin()) {
            $isOwner = (int) $network->owner_id === (int) $actor->user_id;
            $isMember = $actor->networkUsers()
                ->where('network_id', $networkId)
                ->where('status', 'ACTIVE')
                ->exists();
            if (! $isOwner && ! $isMember) {
                throw ValidationException::withMessages([
                    'network_id' => ['غير مصرح لك بإنشاء دعوة ربط لهذه الشبكة.'],
                ]);
            }
        }

        // 3. لا توجد دعوة عامة: يجب تحديد نقطة بيع واحدة وصريحة.
        if (($posId === null && $posUuid === null) || ($posId !== null && $posUuid !== null)) {
            throw ValidationException::withMessages([
                'pos_id' => ['يجب تحديد نقطة بيع واحدة فقط قبل إنشاء دعوة الربط.'],
            ]);
        }

        if ($posUuid !== null) {
            try {
                $pos = $this->posRepository->findByUuid(strtoupper(trim($posUuid)));
                $posId = $pos->pos_id;
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['نقطة البيع المستهدفة غير موجودة.'],
                ]);
            }
        } else {
            try {
                $pos = $this->posRepository->findById((int) $posId);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المستهدفة غير موجودة.'],
                ]);
            }
        }

        if (! $pos instanceof Pos) {
            throw ValidationException::withMessages([
                'pos_id' => ['تعذر تحديد نقطة البيع المستهدفة.'],
            ]);
        }

        $pos->ensureActive();

        // التحقق من عدم وجود ارتباط قائم بالفعل عبر المستودع
        $existingLink = $this->networkPosRepository->findByNetworkAndPos($networkId, $pos->pos_id);
        if ($existingLink) {
            throw ValidationException::withMessages([
                'pos_id' => ['نقطة البيع هذه مرتبطة بالفعل بهذه الشبكة مسبقاً.'],
            ]);
        }

        $duration = max(5, min(60, $durationMinutes));
        $expiresAt = now('Asia/Aden')->addMinutes($duration);
        $challengeUuid = Str::uuid()->toString();
        $nonce = bin2hex(random_bytes(32));

        // 4. بناء حمولة التحدي المشفرة
        $payloadData = [
            'v' => 1,
            'op' => 'pos_network_link',
            'cid' => $challengeUuid,
            'nid' => $networkId,
            'pid' => $posId,
            'nonce' => $nonce,
            'exp' => $expiresAt->timestamp,
        ];

        $ciphertext = $this->cryptoService->encryptForServer(json_encode($payloadData));
        $payloadToken = self::TOKEN_PREFIX.$ciphertext;

        // 5. حفظ التحدي عبر مستودع التحديات
        $challenge = $this->challengeRepository->createChallenge([
            'challenge_uuid' => $challengeUuid,
            'network_id' => $networkId,
            'pos_id' => $posId,
            'created_by_user_id' => $actor->user_id,
            'challenge_nonce' => $nonce,
            'payload_token' => $payloadToken,
            'status' => LinkingChallengeStatus::PENDING,
            'expires_at' => $expiresAt,
        ]);

        return $challenge->load(['network', 'pos']);
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
        ?int $posId = null,
        ?string $posUuid = null
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

        // تحديد نقطة البيع
        if (! $posId && $posUuid) {
            try {
                $pos = $this->posRepository->findByUuid($posUuid);
                $posId = $pos->pos_id;
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['نقطة البيع المحددة غير موجودة.'],
                ]);
            }
        } elseif ($posId) {
            try {
                $pos = $this->posRepository->findById($posId);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير موجودة.'],
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'pos_id' => ['يجب تحديد نقطة البيع المطلوب ربطها.'],
            ]);
        }

        // إذا كانت الدعوة مخصصة لنقطة بيع محددة مسبقاً
        if ((int) $challenge->pos_id !== (int) $posId) {
            throw ValidationException::withMessages([
                'pos_id' => ['دعوة الربط هذه مخصصة لنقطة بيع أخرى ولا يمكن ربطها بنقطة البيع الحالية.'],
            ]);
        }

        $pos->ensureActive();

        if (! $actor->isSuperAdmin()) {
            $isOwner = (int) $pos->owner_id === (int) $actor->user_id;
            $isMember = $actor->posUsers()->where('pos_id', $posId)->exists();
            if (! $isOwner && ! $isMember) {
                throw ValidationException::withMessages([
                    'pos_id' => ['غير مصرح لك بإدارة نقطة البيع هذه.'],
                ]);
            }
        }

        // التحقق من نشاط الشبكة
        $network = $challenge->network;
        $network->ensureActive();

        // التحقق من عدم وجود ارتباط سابق
        $existingLink = $this->networkPosRepository->findByNetworkAndPos($network->network_id, $pos->pos_id);
        if ($existingLink) {
            throw ValidationException::withMessages([
                'pos_id' => ['نقطة البيع هذه مرتبطة بالفعل بهذه الشبكة مسبقاً.'],
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
        ?int $posId = null,
        ?string $posUuid = null
    ): NetworkPos {
        return DB::transaction(function () use ($actor, $challengeUuid, $posId, $posUuid): NetworkPos {
            // جلب التحدي مع القفل المتشائم عبر المستودع
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

            // تحديد نقطة البيع
            if (! $posId && $posUuid) {
                try {
                    $pos = $this->posRepository->findByUuid($posUuid);
                    $posId = $pos->pos_id;
                } catch (ModelNotFoundException) {
                    throw ValidationException::withMessages([
                        'pos_uuid' => ['نقطة البيع المحددة غير موجودة.'],
                    ]);
                }
            } elseif ($posId) {
                try {
                    $pos = $this->posRepository->findById($posId);
                } catch (ModelNotFoundException) {
                    throw ValidationException::withMessages([
                        'pos_id' => ['نقطة البيع المحددة غير موجودة.'],
                    ]);
                }
            } else {
                throw ValidationException::withMessages([
                    'pos_id' => ['يجب تحديد نقطة البيع المطلوب ربطها.'],
                ]);
            }

            if ($challenge->pos_id !== null && (int) $challenge->pos_id !== $posId) {
                throw ValidationException::withMessages([
                    'pos_id' => ['دعوة الربط هذه مخصصة لنقطة بيع أخرى.'],
                ]);
            }

            $pos->ensureActive();

            if (! $actor->isSuperAdmin()) {
                $isOwner = (int) $pos->owner_id === (int) $actor->user_id;
                $isMember = $actor->posUsers()->where('pos_id', $posId)->exists();
                if (! $isOwner && ! $isMember) {
                    throw ValidationException::withMessages([
                        'pos_id' => ['غير مصرح لك بإدارة نقطة البيع هذه.'],
                    ]);
                }
            }

            // التحقق من نشاط الشبكة عبر المستودع
            try {
                $network = $this->networkRepository->findById($challenge->network_id);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'network_id' => ['الشبكة غير موجودة.'],
                ]);
            }
            $network->ensureActive();

            // إنشاء الارتباط والمحفظة عبر NetworkPosService
            $networkPos = $this->networkPosService->create([
                'network_id' => $network->network_id,
                'pos_id' => $pos->pos_id,
                'status' => true,
            ]);

            // استهلاك التحدي عبر المستودع لمنع إعادة الاستخدام
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

            if (! $actor->isSuperAdmin()) {
                $network = $challenge->network;
                $isOwner = (int) $network->owner_id === (int) $actor->user_id;
                $isActiveMember = $actor->networkUsers()
                    ->where('network_id', $challenge->network_id)
                    ->where('status', 'ACTIVE')
                    ->exists();

                if (! $isOwner && ! $isActiveMember) {
                    throw ValidationException::withMessages([
                        'challenge_uuid' => ['غير مصرح لك بإلغاء دعوة الربط هذه.'],
                    ]);
                }
            }

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

        if (! $actor->isSuperAdmin()) {
            $network = $challenge->network;
            $isOwner = (int) $network->owner_id === (int) $actor->user_id;
            $isActiveMember = $actor->networkUsers()
                ->where('network_id', $challenge->network_id)
                ->where('status', 'ACTIVE')
                ->exists();

            if (! $isOwner && ! $isActiveMember) {
                throw ValidationException::withMessages([
                    'challenge_uuid' => ['غير مصرح لك بالاطلاع على حالة دعوة الربط هذه.'],
                ]);
            }
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
