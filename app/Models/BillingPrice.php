<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BillingPrice extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_prices';
    protected $primaryKey = 'price_id';

    protected $fillable = [
        'plan_version_id', 'currency_id', 'billing_interval', 'interval_count',
        'unit_amount_minor', 'setup_fee_minor', 'tax_behavior', 'trial_policy_version_id', 'dunning_policy_version_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'interval_count' => 'integer',
            'unit_amount_minor' => 'integer',
            'setup_fee_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $assertDraft = static function (self $price): void {
            $status = $price->planVersion()->value('status');
            if ($status !== 'DRAFT') {
                throw new LogicException('أسعار النسخ المنشورة أو المؤرشفة غير قابلة للإضافة أو التعديل أو الحذف؛ أنشئ نسخة خطة جديدة.');
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

    public function trialPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(BillingTrialPolicyVersion::class, 'trial_policy_version_id', 'trial_policy_version_id');
    }

    public function dunningPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(BillingDunningPolicyVersion::class, 'dunning_policy_version_id', 'dunning_policy_version_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id');
    }
}
