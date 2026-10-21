<?php

namespace App\Interfaces;

use App\Enums\OtpType;
use App\Models\Otp;

interface OtpRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Create a new OTP code record for a user.
     */
    public function createOtp(
        int $userId,
        string $code,
        OtpType|string $type,
        string $deviceUuid,
        int $expiresInMinutes = 5
    ): Otp;

    /**
     * Get latest active and non-expired OTP for a user.
     */
    public function getLatestValidOtp(int $userId, OtpType|string $type): ?Otp;

    /**
     * Mark an OTP as used.
     */
    public function markAsUsed(int $otpId): bool;

    /**
     * Increment failure attempts for an OTP.
     */
    public function incrementFailureAttempts(int $otpId): int;

    /**
     * Invalidate all existing unused OTPs for a user and type.
     */
    public function invalidateUserOtps(int $userId, OtpType|string $type): int;

    /**
     * Get remaining cooldown seconds before allowing a new OTP request.
     */
    public function getCooldownRemainingSeconds(
        int $userId,
        OtpType|string $type = OtpType::OTP_2FA,
        int $cooldownSeconds = 60
    ): int;

    /**
     * Get count of OTPs requested by a user today for a given type.
     */
    public function getTodayRequestsCount(int $userId, OtpType|string $type): int;

    /**
     * Delete expired or used OTPs older than cutoff.
     */
    public function deleteExpiredOrUsed(\DateTimeInterface $cutoff): int;
}

