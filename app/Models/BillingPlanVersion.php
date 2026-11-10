<?php

namespace App\Models;

use App\Enums\BillingPlanVersionStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlanVersion extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_plan_versions';

    protected $primaryKey = 'plan_version_id';

    protected $fillable = [
        'plan_id',
        'version_number',
        'status',
        'trial_days',
        'terms_snapshot',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => BillingPlanVersionStatus::class,
            'version_number' => 'integer',
            'trial_days' => 'integer',
            'terms_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'plan_id', 'plan_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(BillingPrice::class, 'plan_version_id', 'plan_version_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(BillingPlanFeature::class, 'plan_version_id', 'plan_version_id');
    }
}