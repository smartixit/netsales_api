<?php

namespace App\Interfaces;

use App\Enums\BanType;
use App\Models\SecurityBan;
use DateTimeInterface;

interface SecurityBanRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Check if a user has an active ban.
     */
    public function isUserBanned(int $userId): bool;

    /**
     * Check if a device has an active ban.
     */
    public function isDeviceBanned(int $deviceId): bool;

    /**
     * Get the active ban record for a user.
     */
    public function getActiveBanForUser(int $userId): ?SecurityBan;

    /**
     * Get the active ban record for a device.
     */
    public function getActiveBanForDevice(int $deviceId): ?SecurityBan;

    /**
     * Issue a security ban on a user.
     */
    public function banUser(
        int $userId,
        int $bannedBy,
        BanType|string $banType,
        string $banReason,
        ?DateTimeInterface $endAt = null,
        ?int $deviceId = null
    ): SecurityBan;

    /**
     * Issue a security ban on a device.
     */
    public function banDevice(
        int $deviceId,
        int $bannedBy,
        string $banReason,
        ?DateTimeInterface $endAt = null
    ): SecurityBan;

    /**
     * Lift an existing security ban.
     */
    public function liftBan(int $banId): bool;
}
