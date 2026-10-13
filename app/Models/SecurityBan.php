<?php

namespace App\Models;

use App\Enums\BanType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityBan extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'security_bans';

    protected $primaryKey = 'ban_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'device_id',
        'banned_by',
        'ban_type',
        'ban_reason',
        'start_at',
        'end_at',
    ];

    protected function casts(): array
    {
        return [
            'ban_type' => BanType::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id', 'device_id');
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by', 'user_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $this->scopeActiveBans($query);
    }

    public function scopeActiveBans(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('end_at')->orWhere('end_at', '>', now('Asia/Aden'));
        });
    }

    public function scopeForDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // Checkers
    public function isCurrentlyActive(): bool
    {
        return is_null($this->end_at) || now('Asia/Aden')->lt($this->end_at);
    }

    public function isPermanent(): bool
    {
        return is_null($this->end_at);
    }
}
