<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanFeatureConsumption extends Model
{
    use ExactDateSerialization;

    protected $table = 'subscription_plan_feature_consumptions';

    protected $primaryKey = 'consumption_id';

    protected $keyType = 'int';

    public $incrementing = true;

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

    // Scopes
    public function scopeForSubscription(Builder $query, int $subscriptionId): Builder
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function scopeForFeature(Builder $query, int $featureId): Builder
    {
        return $query->where('feature_id', $featureId);
    }

    // Helpers
    public function recordConsumption(int $amount = 1): void
    {
        $this->increment('consumption_value', $amount, [
            'last_consumption_at' => now('Asia/Aden'),
        ]);
    }

    public function resetConsumption(): void
    {
        $this->update([
            'consumption_value' => 0,
            'last_consumption_at' => null,
        ]);
    }
}
