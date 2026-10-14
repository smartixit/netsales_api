<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTaskRun extends Model
{
    use ExactDateSerialization;

    protected $table = 'scheduled_task_runs';

    public const TASK_DAILY_SUMMARY = 'daily_summary';

    public const TASK_MONTHLY_SUMMARY = 'monthly_summary';

    public const TASK_SUBSCRIPTION_EXPIRY = 'subscription_expiry';

    public const TASK_BACKUP_AUTO = 'backup_auto';

    public const TASK_CLEANUP_EPHEMERAL = 'cleanup_ephemeral';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PARTIALLY_SENT = 'partially_sent';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'task_type',
        'network_id',
        'target_date',
        'status',
        'extra',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'target_date' => 'date:Y-m-d',
            'extra' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }
}
