<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $plan_id
 * @property int $product_id
 * @property string $plan_key
 * @property string $name
 * @property int|null $current_version_id
 * @property bool $is_active
 * @property bool $is_public
 * @property-read BillingProduct $product
 * @property-read BillingPlanVersion|null $currentVersion
 */
class BillingPlan extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'billing_plans';

    protected $primaryKey = 'plan_id';

    protected $fillable = [
        'product_id',
        'plan_key',
        'name',
        'description',
        'current_version_id',
        'is_public',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(BillingProduct::class, 'product_id', 'product_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BillingPlanVersion::class, 'plan_id', 'plan_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(BillingPlanVersion::class, 'current_version_id', 'plan_version_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}