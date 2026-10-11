<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanFeatureConsumption extends Model
{
    use ExactDateSerialization;

    protected $table = 'subscription_plan_feature_consumptions';

    protected $primaryKey = 'consumption_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'subscription_id',
        'feature_id',
        'consumption_value',
        'last_consumption_at',
    ];

    protected function casts(): array
    {
        return [
            'consumption_value' => 'integer',
            'last_consumption_at' => 'datetime',
        ];
    }

    // Relationships
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ProviderSubscription::class, 'subscription_id', 'subscription_id');
    }

    public function planFeature(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanFeature::class, 'feature_id', 'subscription_plan_feature_id');
    }
}
