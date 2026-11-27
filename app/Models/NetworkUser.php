<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MembershipStatus $status
 */
class NetworkUser extends Model
{
    protected $table = 'network_users';

    protected $fillable = [
        'network_id',
        'user_id',
        'role_id',
        'status',
        'last_event_seq',
    ];

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'user_id' => 'integer',
            'role_id' => 'integer',
            'status' => MembershipStatus::class,
            'last_event_seq' => 'integer',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(NetworkMembershipAuditLog::class, 'network_id', 'network_id')
            ->where('user_id', $this->user_id);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::ACTIVE;
    }
}
