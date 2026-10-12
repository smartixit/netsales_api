<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'support_tickets';

    protected $primaryKey = 'ticket_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'pos_id',
        'ticket_uuid',
        'ticket_subject',
    ];

    protected function casts(): array
    {
        return [

        ];
    }

    // Auto generate ticket_uuid
    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (empty($ticket->ticket_uuid)) {
                $ticket->ticket_uuid = BusinessIdentifierGenerator::forTicket($ticket->ticket_id);
            }
        });
    }

    // Relationships
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id', 'ticket_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class, 'ticket_id', 'ticket_id')->latestOfMany('sent_at');
    }

    // Helpers
    public function unreadCount(): int
    {
        return $this->messages()->where('is_read', false)->count();
    }
}
