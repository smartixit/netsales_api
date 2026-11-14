<?php

namespace App\Models;

use App\Enums\BillingFeatureType;
use App\Enums\BillingPlanVersionStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $plan_feature_id
 * @property int $plan_version_id
 * @property string $feature_key
 * @property string $name
 * @property \App\Enums\BillingFeatureType $feature_type
 * @property string $value
 * @property string|null $unit
 * @property string|null $aggregation
 * @property string|null $reset_period
 * @property string $overage_behavior
 * @property array<string, mixed>|null $configuration
 * @property-read BillingPlanVersion $planVersion
 */
class BillingPlanFeature extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_plan_features';
    protected $primaryKey = 'plan_feature_id';

    protected $fillable = [
        'plan_version_id', 'feature_key', 'name', 'feature_type', 'value', 'unit',
        'aggregation', 'reset_period', 'overage_behavior', 'configuration',
    ];

    protected function casts(): array
    {
        return [
            'feature_type' => BillingFeatureType::class,
            'configuration' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $assertDraft = static function (self $feature): void {
            $status = $feature->planVersion()->value('status');
            $status = $status instanceof BillingPlanVersionStatus ? $status->value : $status;
            if ($status !== BillingPlanVersionStatus::DRAFT->value) {
                throw new LogicException('استحقاقات النسخ المنشورة أو المؤرشفة غير قابلة للإضافة أو التعديل أو الحذف؛ أنشئ نسخة خطة جديدة.');
            }
        };

        static::creating($assertDraft);
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(BillingPlanVersion::class, 'plan_version_id', 'plan_version_id');
    }
}
