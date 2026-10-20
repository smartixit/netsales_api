<?php

namespace App\Repositories;

use App\Enums\DeviceType;
use App\Enums\UserDeviceStatus;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class UserDeviceRepository extends BaseRepository implements UserDeviceRepositoryInterface
{
    public function __construct(UserDevice $model)
    {
        parent::__construct($model);
    }

    public function findByUuid(int $userId, string $deviceUuid): ?UserDevice
    {
        return $this->model->newQuery()
            ->forUser($userId)
            ->where('device_uuid', $deviceUuid)
            ->first();
    }

    public function getDevicesForUser(int $userId): Collection
    {
        return $this->model->newQuery()
            ->forUser($userId)
            ->orderByDesc('last_used_at')
            ->get();
    }

    public function registerOrUpdateDevice(
        int $userId,
        string $deviceUuid,
        ?string $deviceName = null,
        string|DeviceType $deviceType = 'UNKNOWN',
        bool $isPrimary = false,
        UserDeviceStatus|string $status = 'ACTIVE',
        ?string $fcmToken = null
    ): UserDevice {
        $deviceTypeValue = $deviceType instanceof DeviceType ? $deviceType->value : $deviceType;
        $statusValue = $status instanceof UserDeviceStatus ? $status->value : $status;

        $existing = $this->findByUuid($userId, $deviceUuid);

        if ($existing) {
            $existing->update([
                'device_name' => $deviceName ?: $existing->device_name,
                'device_type' => $deviceTypeValue ?: $existing->device_type,
                'last_used_at' => Carbon::now('Asia/Aden'),
            ]);

            if ($fcmToken !== null) {
                $this->updateFcmToken($userId, $deviceUuid, $fcmToken);
                $existing->refresh();
            }

            return $existing;
        }

        $device = $this->create([
            'user_id' => $userId,
            'device_uuid' => $deviceUuid,
            'device_name' => $deviceName ?: 'Unknown Device',
            'device_type' => $deviceTypeValue,
            'is_primary' => $isPrimary,
            'status' => $statusValue,
            'last_used_at' => Carbon::now('Asia/Aden'),
        ]);

        if ($fcmToken !== null) {
            $this->updateFcmToken($userId, $deviceUuid, $fcmToken);
            $device->refresh();
        }

        return $device;
    }

    public function getPrimaryDevice(int $userId): ?UserDevice
    {
        return $this->model->newQuery()
            ->forUser($userId)
            ->primary()
            ->first();
    }

    public function touchLastUsed(int $deviceId): void
    {
        $device = $this->model->newQuery()->find($deviceId);
        if ($device) {
            $device->touchLastUsed();
        }
    }

    public function setDeviceStatus(int $deviceId, UserDeviceStatus|string $status): bool
    {
        $statusValue = $status instanceof UserDeviceStatus ? $status->value : $status;

        $device = $this->model->newQuery()->find($deviceId);
        if (! $device) {
            return false;
        }

        if ($statusValue === UserDeviceStatus::INACTIVE->value || $statusValue === UserDeviceStatus::BANNED->value) {
            $device->clearFcmToken();
        }

        return (bool) $device->update(['status' => $statusValue]);
    }

    public function disconnectDevice(int $deviceId, int $userId): bool
    {
        $device = $this->model->newQuery()->forUser($userId)->where('device_id', $deviceId)->first();
        if (! $device) {
            return false;
        }

        $device->clearFcmToken();

        return (bool) $device->delete();
    }

    public function getActiveFcmTokensForUser(int $userId): array
    {
        return $this->model->newQuery()
            ->forUser($userId)
            ->active()
            ->notBanned()
            ->withFcmToken()
            ->pluck('fcm_token')
            ->unique()
            ->values()
            ->all();
    }

    public function updateFcmToken(int $userId, string $deviceUuid, ?string $fcmToken): bool
    {
        $device = $this->findByUuid($userId, $deviceUuid);

        if ($device) {
            // حظر أمني: منع حفظ الرمز إذا كان الجهاز محظوراً وتصفير رمزه
            if ($device->isBanned()) {
                $device->clearFcmToken();

                return false;
            }

            // عزل أمني مؤسسي: تصفير نفس الرمز أو نفس معرف الجهاز لأي مستخدم آخر لمنع تسرب الإشعارات
            if (! empty($fcmToken)) {
                $this->model->newQuery()
                    ->forOtherUsers($userId)
                    ->where(function ($q) use ($fcmToken, $deviceUuid) {
                        $q->where('fcm_token', $fcmToken)
                            ->orWhere('device_uuid', $deviceUuid);
                    })
                    ->clearFcmToken();
            }

            return (bool) $device->update([
                'fcm_token' => $fcmToken,
                'last_used_at' => Carbon::now('Asia/Aden'),
            ]);
        }

        return false;
    }

    public function invalidateFcmToken(string $fcmToken): int
    {
        return $this->model->newQuery()
            ->where('fcm_token', $fcmToken)
            ->clearFcmToken();
    }

    public function clearFcmTokenForDevice(int $userId, string $deviceUuid): bool
    {
        $device = $this->findByUuid($userId, $deviceUuid);

        return $device ? $device->clearFcmToken() : false;
    }
}
