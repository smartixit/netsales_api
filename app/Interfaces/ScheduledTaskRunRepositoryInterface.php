<?php

namespace App\Interfaces;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Models\ScheduledTaskRun;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

interface ScheduledTaskRunRepositoryInterface extends BaseRepositoryInterface
{
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    public function getSummaryStats(): array;

    public function wasExecuted(
        ScheduledTaskType|string $taskType,
        ?int $networkId,
        string $targetDate,
        string $scheduleSlot = 'default'
    ): bool;

    public function recordRun(
        ScheduledTaskType|string $taskType,
        ?int $networkId,
        string $targetDate,
        ScheduledTaskStatus|string $status,
        ?array $extra = null,
        string $scheduleSlot = 'default'
    ): ScheduledTaskRun;

    /**
     * Atomically claims one global scheduled occurrence using the durable database ledger.
     */
    public function claimScheduledSlot(
        ScheduledTaskType $taskType,
        Carbon $scheduledAt,
        int $leaseSeconds = 1800
    ): bool;

    /**
     * Completes a claimed occurrence or makes a failed occurrence retryable after a short delay.
     */
    public function finishScheduledSlot(
        ScheduledTaskType $taskType,
        Carbon $scheduledAt,
        ScheduledTaskStatus $status,
        ?array $extra = null
    ): void;
}
