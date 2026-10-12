<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use ExactDateSerialization;

    protected $table = 'user_sessions';

    protected $primaryKey = 'session_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'logged_at',
        'last_activity_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now('Asia/Aden'));
            });
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // Checkers & Helpers
    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || now('Asia/Aden')->lt($this->expires_at);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now('Asia/Aden')]);
    }

    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now('Asia/Aden')]);
    }
}
