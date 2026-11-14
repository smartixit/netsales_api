<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $dunning_policy_version_id
 * @property int $dunning_policy_id
 * @property string $status
 * @property int $version_number
 * @property int $grace_period_days
 * @property array<int, int> $reminder_schedule_days
 * @property array<int, int> $pre_renewal_alert_days
 * @property bool $auto_suspend_after_grace
 * @property array<int, int> $reminder_schedule_days
 * @property array<int, int> $pre_renewal_alert_days
 * @property-read BillingDunningPolicy $policy
 */
class BillingDunningPolicyVersion extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_dunning_policy_versions';
    protected $primaryKey = 'dunning_policy_version_id';
    protected $fillable = ['dunning_policy_id', 'version_number', 'status', 'grace_period_days', 'auto_suspend_after_grace', 'reminder_schedule_days', 'pre_renewal_alert_days', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['grace_period_days' => 'integer', 'auto_suspend_after_grace' => 'boolean', 'reminder_schedule_days' => 'array', 'pre_renewal_alert_days' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getRawOriginal('status') !== 'DRAFT' && array_diff(array_keys($version->getDirty()), ['updated_at']) !== []) {
                throw new LogicException('إصدار سياسة التحصيل المنشور غير قابل للتعديل؛ أنشئ إصداراً جديداً.');
            }
        });
        static::deleting(fn () => throw new LogicException('إصدارات سياسات التحصيل لا تحذف.'));
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(BillingDunningPolicy::class, 'dunning_policy_id', 'dunning_policy_id');
    }
}
