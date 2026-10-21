<?php

namespace App\Repositories;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Models\ScheduledTaskRun;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة سجلات تنفيذ المهام المجدولة (Scheduled Task Run Repository).
 *
 * @property ScheduledTaskRun $model
 */
class ScheduledTaskRunRepository extends BaseRepository implements ScheduledTaskRunRepositoryInterface
{
    public function __construct(ScheduledTaskRun $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة سجلات المهام المجدولة مع الفلترة والترقيم بالمؤشر.
     */
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

    /**
     * إحصائيات سريعة لحالات المهام المنفذة
     */
    public function getSummaryStats(): array
    {
        $today = Carbon::now('Asia/Aden')->toDateString();

        $todayTotal = $this->model->newQuery()->targetDate($today)->count();
        $todaySent = $this->model->newQuery()->targetDate($today)->successful()->count();
        $todayFailed = $this->model->newQuery()->targetDate($today)->failed()->count();

        $totalAllTime = $this->model->newQuery()->count();
        $totalFailedAllTime = $this->model->newQuery()->failed()->count();

        return [
            'today_total' => $todayTotal,
            'today_sent' => $todaySent,
            'today_failed' => $todayFailed,
            'total_records' => $totalAllTime,
            'total_failed' => $totalFailedAllTime,
            'server_time' => Carbon::now('Asia/Aden')->toDateTimeString(),
        ];
    }

    /**
     * التحقق مما إذا كانت المهمة قد نُفذت بنجاح لشبكة وتاريخ محدد
     */
    public function wasExecuted(ScheduledTaskType|string $taskType, ?int $networkId, string $targetDate): bool
    {
        $taskTypeValue = $taskType instanceof ScheduledTaskType ? $taskType->value : $taskType;

        $query = $this->model->newQuery()
            ->where('task_type', $taskTypeValue)
            ->targetDate($targetDate)
            ->successful();

        if ($networkId !== null) {
            $query->forNetwork($networkId);
        } else {
            $query->whereNull('network_id');
        }

        return $query->exists();
    }

    /**
     * تسجيل أو تحديث تنفيذ مهمة مجدولة لضمان عدم التكرار (Idempotent)
     */
    public function recordRun(
        ScheduledTaskType|string $taskType,
        ?int $networkId,
        string $targetDate,
        ScheduledTaskStatus|string $status,
        ?array $extra = null
    ): ScheduledTaskRun {
        $taskTypeValue = $taskType instanceof ScheduledTaskType ? $taskType->value : $taskType;
        $statusValue = $status instanceof ScheduledTaskStatus ? $status->value : $status;

        /** @var ScheduledTaskRun $run */
        $run = $this->model->newQuery()->updateOrCreate(
            [
                'task_type' => $taskTypeValue,
                'network_id' => $networkId,
                'target_date' => $targetDate,
            ],
            [
                'status' => $statusValue,
                'extra' => $extra,
                'sent_at' => Carbon::now('Asia/Aden'),
            ]
        );

        return $run;
    }
}
