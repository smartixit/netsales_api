<?php

namespace App\Repositories;

use App\Enums\OtpType;
use App\Interfaces\OtpRepositoryInterface;
use App\Models\Otp;
use Carbon\Carbon;

class OtpRepository extends BaseRepository implements OtpRepositoryInterface
{
    public function __construct(Otp $model)
    {
        parent::__construct($model);
    }

    public function createOtp(
        int $userId,
        string $code,
        OtpType|string $type,
        string $deviceUuid,
        int $expiresInMinutes = 5
    ): Otp {
        $typeValue = $type instanceof OtpType ? $type->value : $type;
        $now = Carbon::now('Asia/Aden');

        return $this->create([
            'user_id' => $userId,
            'otp_code' => $code,
            'otp_type' => $typeValue,
            'device_uuid' => $deviceUuid,
            'otp_failure_attempts' => 0,
            'is_used' => false,
            'expires_at' => $now->copy()->addMinutes($expiresInMinutes),
        ]);
    }

    public function getLatestValidOtp(int $userId, OtpType|string $type): ?Otp
    {
        $typeValue = $type instanceof OtpType ? $type->value : $type;

        return $this->model->newQuery()
            ->forUser($userId)
            ->where('otp_type', $typeValue)
            ->active()
            ->latest('otp_id')
            ->first();
    }

    public function markAsUsed(int $otpId): bool
    {
        return (bool) $this->model->newQuery()
            ->where('otp_id', $otpId)
            ->update(['is_used' => true]);
    }

    public function incrementFailureAttempts(int $otpId): int
    {
        $this->model->newQuery()
            ->where('otp_id', $otpId)
            ->increment('otp_failure_attempts');

        $otp = $this->model->newQuery()->find($otpId);

        return $otp ? (int) $otp->otp_failure_attempts : 5;
    }

    public function invalidateUserOtps(int $userId, OtpType|string $type): int
    {
        $typeValue = $type instanceof OtpType ? $type->value : $type;

        return $this->model->newQuery()
            ->forUser($userId)
            ->where('otp_type', $typeValue)
            ->where('is_used', false)
            ->update(['is_used' => true]);
    }

    public function getCooldownRemainingSeconds(
        int $userId,
        OtpType|string $type = OtpType::OTP_2FA,
        int $cooldownSeconds = 60
    ): int {
        $typeValue = $type instanceof OtpType ? $type->value : $type;

        $latestOtp = $this->model->newQuery()
            ->forUser($userId)
            ->where('otp_type', $typeValue)
            ->latest('otp_id')
            ->first();

        if (! $latestOtp || ! $latestOtp->created_at) {
            return 0;
        }

        $nowTimestamp = Carbon::now('Asia/Aden')->timestamp;
        $createdTimestamp = $latestOtp->created_at->timestamp;
        $elapsedSeconds = max(0, $nowTimestamp - $createdTimestamp);

        return $elapsedSeconds < $cooldownSeconds ? (int) ($cooldownSeconds - $elapsedSeconds) : 0;
    }

    public function getTodayRequestsCount(int $userId, OtpType|string $type): int
    {
        $typeValue = $type instanceof OtpType ? $type->value : $type;

        return (int) $this->model->newQuery()
            ->forUser($userId)
            ->where('otp_type', $typeValue)
            ->whereDate('created_at', Carbon::today('Asia/Aden'))
            ->count();
    }

    /**
     * Delete expired or used OTPs older than cutoff.
     */
    public function deleteExpiredOrUsed(\DateTimeInterface $cutoff): int
    {
        return $this->model->newQuery()->where(function ($query) use ($cutoff) {
            $query->where('expires_at', '<', $cutoff)
                ->orWhere(function ($q) use ($cutoff) {
                    $q->where('is_used', true)
                        ->where('created_at', '<', $cutoff);
                });
        })->delete();
    }
}

