<?php

namespace App\Repositories;

use App\Enums\BanType;
use App\Interfaces\SecurityBanRepositoryInterface;
use App\Models\SecurityBan;
use DateTimeInterface;

/**
 * مستودع إدارة الحظر الأمني للمستخدمين والأجهزة (Security Ban Repository).
 *
 * يوفر استعلامات وعمليات الحظر الأمني النشط، الدائم والمؤقت،
 * ويدعم التدقيق الآلي والعمليات المقفلة للحفاظ على سلامة النظام.
 *
 * @property SecurityBan $model
 */
class SecurityBanRepository extends BaseRepository implements SecurityBanRepositoryInterface
{
    public function __construct(SecurityBan $model)
    {
        parent::__construct($model);
    }

    /**
     * التحقق مما إذا كان المستخدم لديه حظر أمني نشط.
     */
    public function isUserBanned(int $userId): bool
    {
        return $this->model->newQuery()
            ->forUser($userId)
            ->active()
            ->exists();
    }

    /**
     * التحقق مما إذا كان الجهاز لديه حظر أمني نشط.
     */
    public function isDeviceBanned(int $deviceId): bool
    {
        return $this->model->newQuery()
            ->forDevice($deviceId)
            ->active()
            ->exists();
    }

    /**
     * جلب سجل الحظر الأمني النشط للمستخدم.
     */
    public function getActiveBanForUser(int $userId): ?SecurityBan
    {
        return $this->model->newQuery()
            ->forUser($userId)
            ->active()
            ->latest('ban_id')
            ->first();
    }

    /**
     * جلب سجل الحظر الأمني النشط للجهاز.
     */
    public function getActiveBanForDevice(int $deviceId): ?SecurityBan
    {
        return $this->model->newQuery()
            ->forDevice($deviceId)
            ->active()
            ->latest('ban_id')
            ->first();
    }

    /**
     * تطبيق حظر أمني على مستخدم.
     */
    public function banUser(
        int $userId,
        int $bannedBy,
        BanType|string $banType,
        string $banReason,
        ?DateTimeInterface $endAt = null,
        ?int $deviceId = null
    ): SecurityBan {
        $banTypeValue = $banType instanceof BanType ? $banType->value : $banType;

        return $this->create([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'banned_by' => $bannedBy,
            'ban_type' => $banTypeValue,
            'ban_reason' => $banReason,
            'start_at' => now('Asia/Aden'),
            'end_at' => $endAt,
        ]);
    }

    /**
     * تطبيق حظر أمني على جهاز.
     */
    public function banDevice(
        int $deviceId,
        int $bannedBy,
        string $banReason,
        ?DateTimeInterface $endAt = null
    ): SecurityBan {
        return $this->create([
            'user_id' => null,
            'device_id' => $deviceId,
            'banned_by' => $bannedBy,
            'ban_type' => $endAt ? BanType::TEMPORARY->value : BanType::PERMANENT->value,
            'ban_reason' => $banReason,
            'start_at' => now('Asia/Aden'),
            'end_at' => $endAt,
        ]);
    }

    /**
     * إلغاء وتفكيك الحظر الأمني.
     */
    public function liftBan(int $banId): bool
    {
        /** @var SecurityBan|null $ban */
        $ban = $this->model->newQuery()->lockForUpdate()->find($banId);
        if (! $ban) {
            return false;
        }

        return (bool) $ban->update([
            'end_at' => now('Asia/Aden'),
        ]);
    }
}
