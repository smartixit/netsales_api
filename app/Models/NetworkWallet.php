<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use Illuminate\Database\Eloquent\Model;

class NetworkWallet extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $wallet): void {
            if (empty($wallet->wallet_uuid)) {
                $wallet->wallet_uuid = BusinessIdentifierGenerator::forWallet(self::class, $wallet->wallet_id);
            }
        });
    }
}
