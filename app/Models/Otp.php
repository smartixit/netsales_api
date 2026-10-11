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

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'otp_code',
        'otp_type',
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
}
