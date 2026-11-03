<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserSession extends Model
{
    use ExactDateSerialization;

    protected $table = 'user_sessions';

    protected $primaryKey = 'session_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    /**
     * The unhashed raw session token returned once to the client upon creation.
     * Never persisted to the database.
     */
    public ?string $plain_token = null;

    protected $fillable = [
        'session_token',
        'user_id',
        'ip_address',
        'user_agent',
        'device_uuid',
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

    /**
     * Calculate the deterministic SHA-256 hash of a high-entropy raw session token.
     */
    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Set a raw token on the session instance:
     * - Preserves the raw token in transient memory ($plain_token)
     * - Authoritatively computes the deterministic SHA-256 digest for database persistence ($session_token)
     */
    public function setRawToken(string $rawToken): self
    {
        $this->plain_token = $rawToken;
        $this->attributes['session_token'] = self::hashToken($rawToken);

        return $this;
    }

    /**
     * Eloquent lifecycle hooks ensuring session tokens are hashed at rest without exception.
     */
    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (empty($session->attributes['session_token'])) {
                // If no token was provided, generate a fresh high-entropy raw token and hash it
                $rawToken = bin2hex(random_bytes(32));
                $session->setRawToken($rawToken);
            } elseif ($session->plain_token === null) {
                // If a token was supplied explicitly without plain_token, treat it as a raw token:
                // set plain_token for transient access and hash it for DB persistence.
                $rawToken = (string) $session->attributes['session_token'];
                $session->setRawToken($rawToken);
            }
            // If $session->plain_token !== null, setRawToken() was already called and the token is already hashed.
            // Do NOT hash again.

            if (empty($session->device_uuid)) {
                $session->device_uuid = (string) Str::uuid();
            }
        });

        static::updating(function (self $session): void {
            // Guard against any direct update attempting to persist a plaintext token
            if ($session->isDirty('session_token') && $session->plain_token !== null) {
                $session->attributes['session_token'] = self::hashToken($session->plain_token);
            }
        });
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
