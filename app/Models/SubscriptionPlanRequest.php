<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanRequest extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (empty($request->request_uuid)) {
                $request->request_uuid = BusinessIdentifierGenerator::forSubscriptionPlanRequest($request->request_id);
            }
        });
    }
}
