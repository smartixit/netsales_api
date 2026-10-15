<?php

namespace App\Interfaces;

use App\Enums\ScheduledTaskType;
use App\Enums\ScheduledTaskStatus;
use App\Models\ScheduledTaskRun;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

interface ScheduledTaskRunRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة سجلات المهام المجدولة مع الفلترة والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * إحصائيات سريعة لحالات المهام المنفذة
     */
    public function getSummaryStats(): array;

    /**
     * التحقق مما إذا كانت المهمة قد نُفذت بنجاح لشبكة وتاريخ محدد
     */
    public function wasExecuted(ScheduledTaskType|string $taskType, ?int $networkId, string $targetDate): bool;

    /**
     * تسجيل أو تحديث تنفيذ مهمة مجدولة لضمان عدم التكرار (Idempotent)
     */
    public function recordRun(
        ScheduledTaskType|string $taskType,
        ?int $networkId,
        string $targetDate,
        ScheduledTaskStatus|string $status,
        ?array $extra = null
    ): ScheduledTaskRun;
}
