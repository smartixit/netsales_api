<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            if (empty($plan->plan_uuid)) {
                $plan->plan_uuid = BusinessIdentifierGenerator::forSubscriptionPlan($plan->plan_id);
            }
        });
    }
}
