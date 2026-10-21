<?php

namespace App\Models;

use App\Enums\OtpType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Otp extends Model
{
    use ExactDateSerialization;

    protected $table = 'otp';

    protected $primaryKey = 'otp_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'otp_code',
        'otp_type',
        'device_uuid',
        'otp_failure_attempts',
        'is_used',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'otp_type' => OtpType::class,
            'otp_failure_attempts' => 'integer',
            'expires_at' => 'datetime',
            'is_used' => 'boolean',
        ];
    }

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // Scopes
    public function scopeExpiresIn(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now('Asia/Aden'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now('Asia/Aden'));
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // Checkers & Helpers
    public function isValid(): bool
    {
        if ($this->is_used) {
            return false;
        }

        if ($this->expires_at === null || now('Asia/Aden')->gt($this->expires_at)) {
            return false;
        }

        return $this->otp_failure_attempts < 5;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now('Asia/Aden')->gt($this->expires_at);
    }

    public function markAsUsed(): void
    {
        $this->update(['is_used' => true]);
    }

    public function incrementFailureAttempts(): void
    {
        $this->increment('otp_failure_attempts');
    }
}
