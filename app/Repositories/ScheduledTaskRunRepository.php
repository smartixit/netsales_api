<?php

namespace App\Repositories;

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
     * التحقق مما إذا كانت المهمة قد نُفذت بنجاح لشبكة وتاريخ محدد
     */
    public function wasExecuted(string $taskType, ?int $networkId, string $targetDate): bool
    {
        $query = $this->model->newQuery()
            ->where('task_type', $taskType)
            ->whereDate('target_date', $targetDate)
            ->whereIn('status', [ScheduledTaskRun::STATUS_SENT, ScheduledTaskRun::STATUS_COMPLETED]);

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
