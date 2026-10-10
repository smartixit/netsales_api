<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $network): void {
            if (empty($network->network_uuid)) {
                $network->network_uuid = BusinessIdentifierGenerator::forNetwork($network->network_id);
            }
        });
    }
}
