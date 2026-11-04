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
use App\Models\User;
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
            $isMember = $actor->networkUsers()->where('network_id', $networkId)->exists();
            if (! $isOwner && ! $isMember) {
                throw ValidationException::withMessages([
                    'network_id' => ['غير مصرح لك بإنشاء دعوة ربط لهذه الشبكة.'],
                ]);
            }
        }

        // 3. التحقق من نقطة البيع المستهدفة إن وجدت عبر المستودع
        if (! $posId && $posUuid) {
            try {
                $pos = $this->posRepository->findByUuid($posUuid);
                $posId = $pos->pos_id;
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_uuid' => ['نقطة البيع المستهدفة غير موجودة.'],
                ]);
            }
        } elseif ($posId) {
            try {
                $pos = $this->posRepository->findById($posId);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المستهدفة غير موجودة.'],
                ]);
            }
        }

        if (isset($pos)) {
            $pos->ensureActive();

            // التحقق من عدم وجود ارتباط قائم بالفعل عبر المستودع
            $existingLink = $this->networkPosRepository->findByNetworkAndPos($networkId, $pos->pos_id);
            if ($existingLink) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع هذه مرتبطة بالفعل بهذه الشبكة مسبقاً.'],
                ]);
            }
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

        if ((int) $challenge->network_id !== (int) $payloadData['nid']
            || $challenge->challenge_nonce !== $payloadData['nonce']
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
        if ($challenge->pos_id !== null && (int) $challenge->pos_id !== $posId) {
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
            'is_targeted' => $challenge->pos_id !== null,
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
        return DB::transaction(function () use ($actor, $challengeUuid, $posId, $posUuid) {
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
        $challenge = $this->challengeRepository->findByUuid($challengeUuid, ['network']);
        if (! $challenge) {
            return false;
        }

        if (! $actor->isSuperAdmin()) {
            $network = $challenge->network;
            $isOwner = (int) $network->owner_id === (int) $actor->user_id;
            $isMember = $actor->networkUsers()->where('network_id', $challenge->network_id)->exists();
            if (! $isOwner && ! $isMember) {
                throw ValidationException::withMessages([
                    'challenge_uuid' => ['غير مصرح لك بإلغاء دعوة الربط هذه.'],
                ]);
            }
        }

        return $this->challengeRepository->markAsRevoked($challenge->challenge_id);
    }

    /**
     * فك تشفير وفحص سلامة بنية حمولة التحدي.
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

        if (! is_array($data)
            || ($data['v'] ?? null) !== 1
            || ($data['op'] ?? null) !== 'pos_network_link'
            || empty($data['cid'])
            || empty($data['nid'])
            || empty($data['nonce'])
        ) {
            throw ValidationException::withMessages([
                'payload' => ['بنية حمولة رمز QR غير مطابقة لمواصفات ربط نقاط البيع.'],
            ]);
        }

        return $data;
    }
}
