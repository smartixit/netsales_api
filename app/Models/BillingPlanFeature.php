<?php

namespace App\Models;

use App\Enums\BillingFeatureType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPlanFeature extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_plan_features';

    protected $primaryKey = 'plan_feature_id';

    protected $fillable = [
        'plan_version_id',
        'feature_key',
        'name',
        'feature_type',
        'value',
        'unit',
        'aggregation',
        'reset_period',
        'overage_behavior',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'feature_type' => BillingFeatureType::class,
            'configuration' => 'array',
        ];
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(BillingPlanVersion::class, 'plan_version_id', 'plan_version_id');
    }
}