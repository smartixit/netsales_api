<?php

namespace App\Models;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Traits\ExactDateSerialization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property ScheduledTaskType|string $task_type
 * @property int|null $network_id
 * @property string $scope_key
 * @property Carbon $target_date
 * @property string $schedule_slot
 * @property ScheduledTaskStatus|string $status
 * @property array|null $extra
 * @property Carbon|null $sent_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $locked_until
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Network|null $network
 */
class ScheduledTaskRun extends Model
{
    use ExactDateSerialization;

    protected $table = 'scheduled_task_runs';

    public const STATUS_RUNNING = ScheduledTaskStatus::RUNNING->value;

    public const STATUS_SENT = ScheduledTaskStatus::SENT->value;

    public const STATUS_FAILED = ScheduledTaskStatus::FAILED->value;

    public const STATUS_COMPLETED = ScheduledTaskStatus::COMPLETED->value;

    public const STATUS_SKIPPED = ScheduledTaskStatus::SKIPPED->value;

    protected $fillable = [
        'task_type',
        'network_id',
        'scope_key',
        'target_date',
        'schedule_slot',
        'status',
        'extra',
        'sent_at',
        'started_at',
        'finished_at',
        'locked_until',
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
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', ScheduledTaskStatus::FAILED);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ScheduledTaskStatus::COMPLETED);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', ScheduledTaskStatus::SENT);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', [ScheduledTaskStatus::SENT, ScheduledTaskStatus::COMPLETED]);
    }

    public function scopeStatus(Builder $query, ScheduledTaskStatus|string $status): Builder
    {
        $val = $status instanceof ScheduledTaskStatus ? $status : ScheduledTaskStatus::tryFrom((string) $status);

        return $val ? $query->where('status', $val) : $query->where('status', $status);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    public function scopeTargetDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('target_date', $date);
    }

    public function isFailed(): bool
    {
        return $this->status === ScheduledTaskStatus::FAILED;
    }

    public function isCompleted(): bool
    {
        return $this->status === ScheduledTaskStatus::COMPLETED;
    }

    public function isSent(): bool
    {
        return $this->status === ScheduledTaskStatus::SENT;
    }
}
