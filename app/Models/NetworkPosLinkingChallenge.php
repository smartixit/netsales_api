<?php

namespace App\Models;

use App\Enums\LinkingChallengeStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkPosLinkingChallenge extends Model
{
    use ExactDateSerialization;

    protected $table = 'network_pos_linking_challenges';

    protected $primaryKey = 'challenge_id';

    protected $fillable = [
        'challenge_uuid',
        'network_id',
        'pos_id',
        'created_by_user_id',
        'challenge_nonce',
        'payload_token',
        'status',
        'expires_at',
        'consumed_at',
        'consumed_by_user_id',
        'consumed_by_pos_id',
    ];

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'pos_id' => 'integer',
            'created_by_user_id' => 'integer',
            'consumed_by_user_id' => 'integer',
            'consumed_by_pos_id' => 'integer',
            'status' => LinkingChallengeStatus::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_user_id', 'user_id');
    }

    public function consumedPos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'consumed_by_pos_id', 'pos_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LinkingChallengeStatus::PENDING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LinkingChallengeStatus::PENDING)
            ->where('expires_at', '>', now('Asia/Aden'));
    }

    public function isPending(): bool
    {
        return $this->status === LinkingChallengeStatus::PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
