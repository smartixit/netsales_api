<?php

namespace App\Models;

use App\Enums\ScheduledTaskType;
use App\Enums\ScheduledTaskStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property ScheduledTaskType|string $task_type
 * @property int|null $network_id
 * @property \Carbon\Carbon $target_date
 * @property ScheduledTaskStatus|string $status
 * @property array|null $extra
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Network|null $network
 */
class ScheduledTaskRun extends Model
{
    use ExactDateSerialization;

    protected $table = 'scheduled_task_runs';

    public const STATUS_SENT = ScheduledTaskStatus::SENT->value;

    public const STATUS_FAILED = ScheduledTaskStatus::FAILED->value;

    public const STATUS_COMPLETED = ScheduledTaskStatus::COMPLETED->value;

    public const STATUS_SKIPPED = ScheduledTaskStatus::SKIPPED->value;

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
            'task_type' => ScheduledTaskType::class,
            'status' => ScheduledTaskStatus::class,
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
