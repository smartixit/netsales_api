<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkMembershipAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'network_membership_audit_logs';

    protected $primaryKey = 'audit_id';

    protected $fillable = [
        'network_id',
        'user_id',
        'event_seq',
        'action',
        'actor_id',
        'old_state',
        'new_state',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'user_id' => 'integer',
            'event_seq' => 'integer',
            'actor_id' => 'integer',
            'old_state' => 'array',
            'new_state' => 'array',
            'created_at' => 'datetime',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'user_id');
    }
}

