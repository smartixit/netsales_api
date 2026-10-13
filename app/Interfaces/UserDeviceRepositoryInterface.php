<?php

namespace App\Interfaces;

use App\Enums\DeviceType;
use App\Enums\UserDeviceStatus;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Collection;

interface UserDeviceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find a user device by UUID.
     */
    public function findByUuid(int $userId, string $deviceUuid): ?UserDevice;

    /**
     * Get all devices belonging to a user.
     *
     * @return Collection<int, UserDevice>
     */
    public function getDevicesForUser(int $userId): Collection;

    /**
     * Register a new device or update existing device information.
     */
    public function registerOrUpdateDevice(
        int $userId,
        string $deviceUuid,
        ?string $deviceName = null,
        string|DeviceType $deviceType = 'UNKNOWN',
        bool $isPrimary = false,
        UserDeviceStatus|string $status = 'ACTIVE'
    ): UserDevice;

    /**
     * Get the primary device for a user.
     */
    public function getPrimaryDevice(int $userId): ?UserDevice;

    /**
     * Update the last used timestamp for a device.
     */
    public function touchLastUsed(int $deviceId): void;

    /**
     * Update status of a device.
     */
    public function setDeviceStatus(int $deviceId, UserDeviceStatus|string $status): bool;

    /**
     * Disconnect/remove a device for a specific user.
     */
    public function disconnectDevice(int $deviceId, int $userId): bool;
}
