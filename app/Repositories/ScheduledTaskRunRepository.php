<?php

namespace App\Repositories;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Models\ScheduledTaskRun;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * مستودع إدارة سجلات تنفيذ المهام المجدولة.
 *
 * @property ScheduledTaskRun $model
 */
class ScheduledTaskRunRepository extends BaseRepository implements ScheduledTaskRunRepositoryInterface
{
    public function __construct(ScheduledTaskRun $model)
    {
        parent::__construct($model);
    }

    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $defaultRelations = ['network'];
        $withRelations = ! empty($relations) ? $relations : $defaultRelations;
        $query = $this->model->newQuery()->with($withRelations);

        if (! empty($filters['task_type'])) {
            $taskTypeValue = $filters['task_type'] instanceof ScheduledTaskType
                ? $filters['task_type']->value
                : (string) $filters['task_type'];
            $query->where('task_type', $taskTypeValue);
        }

        if (isset($filters['network_id'])) {
            if ($filters['network_id'] === 'null' || $filters['network_id'] === null) {
                $query->whereNull('network_id');
            } else {
                $query->forNetwork((int) $filters['network_id']);
            }
        }

        if (! empty($filters['target_date'])) {
            $query->whereDate('target_date', $filters['target_date']);
        }

        if (! empty($filters['status'])) {
            $query->status($filters['status']);
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('id', 'desc')->get();
        }

        return $query->orderBy('id', 'desc')->cursorPaginate($perPage);
    }

    public function getSummaryStats(): array
    {
        $today = Carbon::now('Asia/Aden')->toDateString();

        return [
            'today_total' => $this->model->newQuery()->targetDate($today)->count(),
            'today_sent' => $this->model->newQuery()->targetDate($today)->successful()->count(),
            'today_failed' => $this->model->newQuery()->targetDate($today)->failed()->count(),
            'total_records' => $this->model->newQuery()->count(),
            'total_failed' => $this->model->newQuery()->failed()->count(),
            'server_time' => Carbon::now('Asia/Aden')->toDateTimeString(),
        ];
    }

    public function wasExecuted(
        ScheduledTaskType|string $taskType,
        ?int $networkId,
        string $targetDate,
        string $scheduleSlot = 'default'
    ): bool {
        $taskTypeValue = $taskType instanceof ScheduledTaskType ? $taskType->value : $taskType;

        return $this->model->newQuery()
            ->where('task_type', $taskTypeValue)
            ->where('scope_key', $this->scopeKey($networkId))
            ->where('schedule_slot', $scheduleSlot)
            ->targetDate($targetDate)
            ->successful()
            ->exists();
    }

    public function recordRun(
        ScheduledTaskType|string $taskType,
        ?int $networkId,
        string $targetDate,
        ScheduledTaskStatus|string $status,
        ?array $extra = null,
        string $scheduleSlot = 'default'
    ): ScheduledTaskRun {
        $taskTypeValue = $taskType instanceof ScheduledTaskType ? $taskType->value : $taskType;
        $statusValue = $status instanceof ScheduledTaskStatus ? $status->value : $status;

        return $this->model->newQuery()->updateOrCreate(
            [
                'task_type' => $taskTypeValue,
                'scope_key' => $this->scopeKey($networkId),
                'target_date' => $targetDate,
                'schedule_slot' => $scheduleSlot,
            ],
            [
                'network_id' => $networkId,
                'status' => $statusValue,
                'extra' => $extra,
                'sent_at' => Carbon::now('Asia/Aden'),
                'finished_at' => Carbon::now('Asia/Aden'),
                'locked_until' => null,
            ]
        );
    }

    public function claimScheduledSlot(
        ScheduledTaskType $taskType,
        Carbon $scheduledAt,
        int $leaseSeconds = 1800
    ): bool {
        return DB::transaction(function () use ($taskType, $scheduledAt, $leaseSeconds): bool {
            $now = Carbon::now('Asia/Aden');
            $targetDate = $scheduledAt->toDateString();
            $scheduleSlot = $scheduledAt->format('Y-m-d H:i:s');

            $inserted = DB::table($this->model->getTable())->insertOrIgnore([
                'task_type' => $taskType->value,
                'network_id' => null,
                'scope_key' => 'global',
                'target_date' => $targetDate,
                'schedule_slot' => $scheduleSlot,
                'status' => ScheduledTaskStatus::RUNNING->value,
                'started_at' => $now,
                'locked_until' => $now->copy()->addSeconds(max(60, $leaseSeconds)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $run = $this->model->newQuery()
                ->where('task_type', $taskType->value)
                ->where('scope_key', 'global')
                ->whereDate('target_date', $targetDate)
                ->where('schedule_slot', $scheduleSlot)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inserted > 0) {
                return true;
            }

            $status = $run->status instanceof ScheduledTaskStatus
                ? $run->status
                : ScheduledTaskStatus::tryFrom((string) $run->status);

            if ($status?->isSuccess()) {
                return false;
            }

            if (in_array($status, [ScheduledTaskStatus::RUNNING, ScheduledTaskStatus::FAILED], true)
                && $run->locked_until?->isFuture()) {
                return false;
            }

            $run->status = ScheduledTaskStatus::RUNNING;
            $run->started_at = $now;
            $run->finished_at = null;
            $run->locked_until = $now->copy()->addSeconds(max(60, $leaseSeconds));
            $run->sent_at = null;
            $run->extra = null;
            $run->save();

            return true;
        }, 3);
    }

    public function finishScheduledSlot(
        ScheduledTaskType $taskType,
        Carbon $scheduledAt,
        ScheduledTaskStatus $status,
        ?array $extra = null
    ): void {
        $now = Carbon::now('Asia/Aden');
        $scheduleSlot = $scheduledAt->format('Y-m-d H:i:s');

        /** @var ScheduledTaskRun|null $run */
        $run = $this->model->newQuery()
            ->where('task_type', $taskType->value)
            ->where('scope_key', 'global')
            ->whereDate('target_date', $scheduledAt->toDateString())
            ->where('schedule_slot', $scheduleSlot)
            ->first();

        if (! $run) {
            return;
        }

        $run->status = $status;
        $run->extra = $extra;
        $run->sent_at = $now;
        $run->finished_at = $now;
        $run->locked_until = $status === ScheduledTaskStatus::FAILED
            ? $now->copy()->addMinute()
            : null;
        $run->save();
    }

    private function scopeKey(?int $networkId): string
    {
        return $networkId === null ? 'global' : 'network:'.$networkId;
    }
}
