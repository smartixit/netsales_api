<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosUser extends Model
{
    protected $table = 'pos_users';

    protected $fillable = [
        'pos_id',
        'user_id',
        'role_id',
        'status',
        'last_event_seq',
    ];

    protected function casts(): array
    {
        return [
            'pos_id' => 'integer',
            'user_id' => 'integer',
            'role_id' => 'integer',
            'last_event_seq' => 'integer',
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PosMembershipAuditLog::class, 'pos_id', 'pos_id')
            ->where('user_id', $this->user_id);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
