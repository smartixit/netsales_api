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
        UserDeviceStatus|string $status = 'ACTIVE'
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

            return $existing;
        }

        return $this->create([
            'user_id' => $userId,
            'device_uuid' => $deviceUuid,
            'device_name' => $deviceName ?: 'Unknown Device',
            'device_type' => $deviceTypeValue,
            'is_primary' => $isPrimary,
            'status' => $statusValue,
            'last_used_at' => Carbon::now('Asia/Aden'),
        ]);
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
        $this->model->newQuery()
            ->where('device_id', $deviceId)
            ->update(['last_used_at' => Carbon::now('Asia/Aden')]);
    }

    public function setDeviceStatus(int $deviceId, UserDeviceStatus|string $status): bool
    {
        $statusValue = $status instanceof UserDeviceStatus ? $status->value : $status;

        return (bool) $this->model->newQuery()
            ->where('device_id', $deviceId)
            ->update(['status' => $statusValue]);
    }

    public function disconnectDevice(int $deviceId, int $userId): bool
    {
        return (bool) $this->model->newQuery()
            ->forUser($userId)
            ->where('device_id', $deviceId)
            ->delete();
    }
}
