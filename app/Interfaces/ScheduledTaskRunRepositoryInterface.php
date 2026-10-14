<?php

namespace App\Interfaces;

use App\Models\ScheduledTaskRun;

interface ScheduledTaskRunRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * التحقق مما إذا كانت المهمة قد نُفذت بنجاح لشبكة وتاريخ محدد
     */
    public function wasExecuted(string $taskType, ?int $networkId, string $targetDate): bool;

    /**
     * تسجيل أو تحديث تنفيذ مهمة مجدولة لضمان عدم التكرار (Idempotent)
     */
    public function recordRun(string $taskType, ?int $networkId, string $targetDate, string $status, ?array $extra = null): ScheduledTaskRun;
}
