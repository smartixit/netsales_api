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
        int $expiresInMinutes = 5
    ): Otp {
        $typeValue = $type instanceof OtpType ? $type->value : $type;
        $now = Carbon::now('Asia/Aden');

        return $this->create([
            'user_id' => $userId,
            'otp_code' => $code,
            'otp_type' => $typeValue,
            'otp_failure_attempts' => 0,
            'is_used' => false,
            'expires_at' => $now->copy()->addMinutes($expiresInMinutes),
        ]);
    }

    public function getLatestValidOtp(int $userId, OtpType|string $type): ?Otp
    {
        $typeValue = $type instanceof OtpType ? $type->value : $type;

        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('otp_type', $typeValue)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now('Asia/Aden'))
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
            ->where('user_id', $userId)
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
            ->where('user_id', $userId)
            ->where('otp_type', $typeValue)
            ->latest('otp_id')
            ->first();

        if (! $latestOtp || ! $latestOtp->created_at) {
            return 0;
        }

        $elapsedSeconds = Carbon::now('Asia/Aden')->diffInSeconds($latestOtp->created_at);

        return $elapsedSeconds < $cooldownSeconds ? ($cooldownSeconds - $elapsedSeconds) : 0;
    }

    public function getTodayRequestsCount(int $userId, OtpType|string $type): int
    {
        $typeValue = $type instanceof OtpType ? $type->value : $type;

        return (int) $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('otp_type', $typeValue)
            ->whereDate('created_at', Carbon::today('Asia/Aden'))
            ->count();
    }
}
