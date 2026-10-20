<?php

namespace App\Models;

use App\Enums\DeviceType;
use App\Enums\UserDeviceStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserDevice extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'user_devices';

    protected $primaryKey = 'device_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'device_uuid',
        'device_name',
        'device_type',
        'fcm_token',
        'is_primary',
        'status',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'last_used_at' => 'datetime',
            'device_type' => DeviceType::class,
            'status' => UserDeviceStatus::class,
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function securityBans(): HasMany
    {
        return $this->hasMany(SecurityBan::class, 'device_id', 'device_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserDeviceStatus::ACTIVE);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', UserDeviceStatus::INACTIVE);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeBanned(Builder $query): Builder
    {
        return $query->where('status', UserDeviceStatus::BANNED);
    }

    public function scopeNotBanned(Builder $query): Builder
    {
        return $query->where('status', '!=', UserDeviceStatus::BANNED)
            ->whereDoesntHave('securityBans', fn (Builder $q) => $q->activeBans());
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOtherUsers(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', '!=', $userId);
    }

    public function scopeDeviceUuid(Builder $query, string $deviceUuid): Builder
    {
        return $query->where('device_uuid', $deviceUuid);
    }

    public function scopeWithFcmToken(Builder $query): Builder
    {
        return $query->whereNotNull('fcm_token')->where('fcm_token', '!=', '');
    }

    public function scopeClearFcmToken(Builder $query): int
    {
        return $query->update(['fcm_token' => null]);
    }

    // Checkers & Helpers
    public function clearFcmToken(): bool
    {
        return (bool) $this->update(['fcm_token' => null]);
    }
    public function isActive(): bool
    {
        return $this->status === UserDeviceStatus::ACTIVE;
    }

    public function ensureActive(): void
    {
        if (! $this->isActive()) {
            throw ValidationException::withMessages([
                'device_uuid' => ["جهاز المستخدم غير مفعّل أو غير نشط حالياً."],
            ]);
        }

        if ($this->isBanned()) {
            throw ValidationException::withMessages([
                'device_uuid' => ["هذا الجهاز محظور أمنياً من النظام."],
            ]);
        }
    }

    public function isPrimary(): bool
    {
        return (bool) $this->is_primary;
    }

    public function isBanned(): bool
    {
        if ($this->status === UserDeviceStatus::BANNED) {
            return true;
        }

        return $this->securityBans()->activeBans()->exists();
    }

    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now('Asia/Aden')]);
    }

    public function setAsPrimary(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('user_id', $this->user_id)
                ->where('device_id', '!=', $this->device_id)
                ->update(['is_primary' => false]);

            $this->update(['is_primary' => true]);
        });
    }
}
