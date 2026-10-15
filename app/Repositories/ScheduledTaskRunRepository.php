<?php

namespace App\Repositories;

use App\Enums\ScheduledTaskStatus;
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
            $query->where('task_type', (string) $filters['task_type']);
        }

        if (isset($filters['network_id'])) {
            if ($filters['network_id'] === 'null' || $filters['network_id'] === null) {
                $query->whereNull('network_id');
            } else {
                $query->where('network_id', (int) $filters['network_id']);
            }
        }

        if (! empty($filters['target_date'])) {
            $query->whereDate('target_date', $filters['target_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
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

        $todayTotal = $this->model->newQuery()->whereDate('target_date', $today)->count();
        $todaySent = $this->model->newQuery()->whereDate('target_date', $today)->whereIn('status', [ScheduledTaskStatus::STATUS_SENT->value, ScheduledTaskStatus::STATUS_COMPLETED->value])->count();
        $todayFailed = $this->model->newQuery()->whereDate('target_date', $today)->where('status', ScheduledTaskStatus::STATUS_FAILED->value)->count();

        $totalAllTime = $this->model->newQuery()->count();
        $totalFailedAllTime = $this->model->newQuery()->where('status', ScheduledTaskStatus::STATUS_FAILED->value)->count();

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
    public function wasExecuted(string $taskType, ?int $networkId, string $targetDate): bool
    {
        $query = $this->model->newQuery()
            ->where('task_type', $taskType)
            ->whereDate('target_date', $targetDate)
            ->whereIn('status', [ScheduledTaskStatus::STATUS_SENT->value, ScheduledTaskStatus::STATUS_COMPLETED->value]);

        if ($networkId !== null) {
            $query->where('network_id', $networkId);
        } else {
            $query->whereNull('network_id');
        }

        return $query->exists();
    }

    /**
     * تسجيل أو تحديث تنفيذ مهمة مجدولة لضمان عدم التكرار (Idempotent)
     */
    public function recordRun(string $taskType, ?int $networkId, string $targetDate, string $status, ?array $extra = null): ScheduledTaskRun
    {
        /** @var ScheduledTaskRun $run */
        $run = $this->model->newQuery()->updateOrCreate(
            [
                'task_type' => $taskType,
                'network_id' => $networkId,
                'target_date' => $targetDate,
            ],
            [
                'status' => $status,
                'extra' => $extra,
                'sent_at' => Carbon::now('Asia/Aden'),
            ]
        );

        return $run;
    }
}
