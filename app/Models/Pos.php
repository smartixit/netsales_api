<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use Illuminate\Database\Eloquent\Model;

class Pos extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $pos): void {
            if (empty($pos->pos_uuid)) {
                $pos->pos_uuid = BusinessIdentifierGenerator::forPOS($pos->pos_id);
            }
        });
    }
}
