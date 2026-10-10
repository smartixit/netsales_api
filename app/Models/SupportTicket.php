<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (empty($ticket->ticket_uuid)) {
                $ticket->ticket_uuid = BusinessIdentifierGenerator::forTicket($ticket->ticket_id);
            }
        });
    }
}
