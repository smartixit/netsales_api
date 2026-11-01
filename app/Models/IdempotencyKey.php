<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'user_id',
        'tenant_type',
        'tenant_id',
        'endpoint',
        'idempotency_key',
        'request_hash',
        'status',
        'response_code',
        'response_body',
        'response_headers',
        'fencing_token',
        'lease_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tenant_id' => 'integer',
            'response_code' => 'integer',
            'response_body' => 'array',
            'response_headers' => 'array',
            'fencing_token' => 'integer',
            'lease_expires_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isLeaseExpired(): bool
    {
        return $this->lease_expires_at->isPast();
    }
}
