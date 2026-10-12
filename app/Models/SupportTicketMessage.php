<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicketMessage extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'support_ticket_messages';

    protected $primaryKey = 'message_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'ticket_id',
        'sent_by',
        'message_body',
        'sent_at',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    // Relationships
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id', 'ticket_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by', 'user_id');
    }

    // Scopes
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    // Helpers
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now('Asia/Aden'),
        ]);
    }
}
