<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosMembershipAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'pos_membership_audit_logs';

    protected $primaryKey = 'audit_id';

    protected $fillable = [
        'pos_id',
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
            'pos_id' => 'integer',
            'user_id' => 'integer',
            'event_seq' => 'integer',
            'actor_id' => 'integer',
            'old_state' => 'array',
            'new_state' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
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

