<?php

namespace App\Models;

use App\Enums\ProviderSubscriptionStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderSubscription extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'provider_subscriptions';

    protected $primaryKey = 'subscription_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'plan_id',
        'subscription_start_date',
        'subscription_end_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'=>ProviderSubscriptionStatus::class,
            'subscription_start_date' => 'datetime',
            'subscription_end_date' => 'datetime',
        ];
    }

    // Relationships
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(
            SubscriptionPlanFeatureConsumption::class,
            'subscription_id',
            'subscription_id'
        );
    }
}
