<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    public $timestamps = false;

    protected $table = 'outbox_messages';

    protected $fillable = [
        'message_uuid',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'headers',
        'status',
        'retry_count',
        'max_retries',
        'lease_token',
        'lease_expires_at',
        'last_error',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'headers' => 'array',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
            'lease_expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'PROCESSING';
    }

    public function isPublished(): bool
    {
        return $this->status === 'PUBLISHED';
    }
}

