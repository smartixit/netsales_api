<?php

namespace App\Services;

use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * خدمة استعلام ومتابعة سجلات المهام المجدولة (Scheduled Task Run Service).
 */
class ScheduledTaskRunService
{
    public function __construct(
        protected ScheduledTaskRunRepositoryInterface $repository
    ) {}

    /**
     * جلب قائمة سجلات المهام المجدولة مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->repository->getFiltered($filters, ['network']);
    }

    /**
     * إحصائيات سريعة لحالات المهام المنفذة لليوم ولكافة الأوقات.
     */
    public function getSummaryStats(): array
    {
        return $this->repository->getSummaryStats();
    }
}
