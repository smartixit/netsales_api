<?php

namespace App\Services;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Interfaces\SystemSettingRepositoryInterface;
use App\Models\ScheduledTaskRun;
use App\Services\Backup\BackupSettingsService;
use App\Services\SystemLoggerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * محرك التعويض الذكي للمهام المجدولة (Smart Scheduler Catch-up Engine).
 *
 * يحل مشكلة تفويت المهام المجدولة عند غياب الـ OS Crontab المستمر أو عند إعادة تشغيل السيرفر.
 * يفحص استحقاق المهام (ساعية، يومية، أسبوعية، شهرية) وينفذ المهام الفائتة فوراً
 * مع حماية تامة من التكرار والتداخل (Anti-Overlapping) وتسجيل السجلات في scheduled_task_runs.
 */
class SmartSchedulerCatchupService
{
    public function __construct(
        protected ScheduledTaskRunRepositoryInterface $taskRunRepository,
        protected SystemSettingRepositoryInterface $systemSettingRepository,
        protected BackupSettingsService $backupSettings
    ) {}

    /**
     * جلب سجل كل المهام المجدولة في نظام NetSales وقواعد استحقاقها من ScheduledTaskType Enum
     *
     * @return array<string, array{type: ScheduledTaskType, name: string, command: string, frequency: string, target_hour: int}>
     */
    public function getTasks(): array
    {
        $tasks = [];
        foreach (ScheduledTaskType::cases() as $type) {
            $tasks[$type->value] = [
                'type' => $type,
                'name' => $type->label(),
                'command' => $type->command(),
                'frequency' => $type->frequency(),
                'target_hour' => $type->defaultTargetHour(),
            ];
        }

        return $tasks;
    }

    /**
     * تشغيل فحص التعويض لكل المهام المجدولة
     */
    public function runAllCatchupTasks(bool $force = false, ?string $singleTask = null): array
    {
        $now = Carbon::now('Asia/Aden');
        $executedTasks = [];
        $skippedTasks = [];

        foreach ($this->getTasks() as $key => $config) {
            if ($singleTask && $key !== $singleTask && $config['command'] !== $singleTask) {
                continue;
            }

            $isDue = $force || $this->isTaskDue($key, $config, $now);

            if (! $isDue) {
                $skippedTasks[$key] = [
                    'name' => $config['name'],
                    'reason' => 'not_due_or_already_executed',
                    'last_run' => $this->getLastRunKey($key),
                ];

                continue;
            }

            // قفل تزامني لمنع التداخل بين العمليات المتزامنة
            $lockKey = "smart_catchup_lock_{$key}";
            $lock = Cache::lock($lockKey, 180);

            if (! $lock->get()) {
                $skippedTasks[$key] = [
                    'name' => $config['name'],
                    'reason' => 'locked_by_concurrent_process',
                ];

                continue;
            }

            try {
                if (! $force && ! $this->isTaskDue($key, $config, $now)) {
                    continue;
                }

                // تنفيذ الأمر الفعلي
                $exitCode = Artisan::call($config['command']);
                $output = trim(Artisan::output());

                $status = $exitCode === 0 ? ScheduledTaskStatus::COMPLETED : ScheduledTaskStatus::FAILED;

                // تسجيل إتمام التنفيذ في قاعدة البيانات وذاكرة النظام
                $this->markTaskExecuted($key, $config, $now, $status, ['output' => $output]);

                $executedTasks[$key] = [
                    'name' => $config['name'],
                    'command' => $config['command'],
                    'status' => $exitCode === 0 ? 'success' : 'failed',
                    'exit_code' => $exitCode,
                    'output' => $output,
                    'executed_at' => $now->toDateTimeString(),
                ];

                SystemLoggerService::log(
                    module: 'scheduler',
                    action: 'catchup_task_executed',
                    newData: [
                        'task' => $config['name'],
                        'command' => $config['command'],
                        'exit_code' => $exitCode,
                        'output' => $output,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("❌ [SmartSchedulerCatchup] فشل في تنفيذ {$config['command']}: ".$e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->markTaskExecuted($key, $config, $now, ScheduledTaskStatus::FAILED, ['error' => $e->getMessage()]);

                $executedTasks[$key] = [
                    'name' => $config['name'],
                    'command' => $config['command'],
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'executed_at' => $now->toDateTimeString(),
                ];
            } finally {
                optional($lock)->release();
            }
        }

        // فحص وتصريف مهام الطابور المعلقة ذاتياً عند غياب معالج الطوابير الدائم
        if ($drainResult = $this->drainPendingQueueJobs($now)) {
            $executedTasks['queue_worker_drain'] = $drainResult;
        }

        return [
            'executed' => $executedTasks,
            'skipped' => $skippedTasks,
            'server_time' => $now->toDateTimeString(),
        ];
    }

    /**
     * فحص وتصريف مهام الطابور المعلقة بأمان عند غياب الـ Worker الدائم (Self-Healing Queue Worker)
     *
     * @return array<string, mixed>|null
     */
    protected function drainPendingQueueJobs(Carbon $now): ?array
    {
        if (config('queue.default') === 'sync' || ! Schema::hasTable('jobs')) {
            return null;
        }

        if (! DB::table('jobs')->exists()) {
            return null;
        }

        $jobLock = Cache::lock('smart_catchup_queue_worker_lock', 60);

        if (! $jobLock->get()) {
            return null;
        }

        try {
            $exitCode = Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => 15,
                '--tries' => 3,
            ]);

            $output = trim(Artisan::output());

            SystemLoggerService::log(
                module: 'scheduler',
                action: 'queue_worker_drained',
                newData: [
                    'command' => 'queue:work --stop-when-empty',
                    'exit_code' => $exitCode,
                    'output' => $output,
                ]
            );

            return [
                'name' => 'معالجة وتصريف طوابير المهام المعلقة',
                'command' => 'queue:work --stop-when-empty',
                'status' => $exitCode === 0 ? 'success' : 'failed',
                'exit_code' => $exitCode,
                'output' => $output,
                'executed_at' => $now->toDateTimeString(),
            ];
        } catch (\Throwable $e) {
            Log::warning('❌ [SmartSchedulerCatchup] تعذر تصريف الطابور: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        } finally {
            optional($jobLock)->release();
        }
    }

    /**
     * هل المهمة مستحقة للتنفيذ الآن؟
     */
    public function isTaskDue(string $key, array $config, Carbon $now): bool
    {
        // التحقق الخاص بمهمة النسخ الاحتياطي التلقائي
        if ($key === 'backup_auto') {
            if (! $this->backupSettings->enabled()) {
                return false;
            }

            [$schedHour, $schedMinute] = array_pad(array_map('intval', explode(':', $this->backupSettings->scheduleTime())), 2, 0);

            // أ) إذا كنا قبل ساعة الاستحقاق، هل فات موعد الأمس؟
            if ($now->hour < $schedHour) {
                $yesterday = $now->copy()->subDay();

                return ! $this->backupSettings->isBackupCompletedToday($yesterday);
            }

            // ب) هل حان موعد اليوم ولم يكتمل بعد؟
            return ! $this->backupSettings->isBackupCompletedToday($now);
        }

        $frequency = $config['frequency'];
        $targetHour = $config['target_hour'] ?? 0;
        $lastRun = $this->getLastRunKey($key);

        switch ($frequency) {
            case 'hourly':
                $currentHourStr = $now->format('Y-m-d H');

                return $lastRun !== $currentHourStr;

            case 'daily':
                $todayStr = $now->toDateString();
                if ($lastRun === $todayStr) {
                    return false;
                }

                return $now->hour >= $targetHour;

            case 'weekly':
                $currentWeekStr = $now->format('o-\WW');

                return $lastRun !== $currentWeekStr;

            case 'monthly':
                $currentMonthStr = $now->format('Y-m');

                return $lastRun !== $currentMonthStr;

            default:
                return false;
        }
    }

    /**
     * تسجيل أن المهمة نُفذت في الدورة الحالية وتخزينها في قاعدة البيانات والذاكرة
     */
    public function markTaskExecuted(
        string $key,
        array $config,
        Carbon $now,
        ScheduledTaskStatus|string $status = ScheduledTaskStatus::COMPLETED,
        ?array $extra = null
    ): void
    {
        $frequency = $config['frequency'];
        $targetDate = $now->toDateString();

        if ($key === 'backup_auto') {
            [$schedHour] = array_pad(array_map('intval', explode(':', $this->backupSettings->scheduleTime())), 1, 0);
            if ($now->hour < $schedHour) {
                $targetDate = $now->copy()->subDay()->toDateString();
            }
        }

        $value = match ($frequency) {
            'hourly' => $now->format('Y-m-d H'),
            'daily' => $targetDate,
            'weekly' => $now->format('o-\WW'),
            'monthly' => $now->format('Y-m'),
            default => $now->toDateTimeString(),
        };

        // 1. تسجيل القيمة في الكاش لسرعة التحقق
        $cacheKey = "scheduler_last_run_{$key}";
        Cache::put($cacheKey, $value, now()->addDays(35));

        // 2. توثيق التنفيذ في جدول scheduled_task_runs لضمان عدم التكرار (Idempotency)
        try {
            $this->taskRunRepository->recordRun(
                taskType: $config['type'] ?? $key,
                networkId: null,
                targetDate: $targetDate,
                status: $status,
                extra: array_merge(['frequency' => $frequency, 'value' => $value], $extra ?? [])
            );
        } catch (\Throwable $e) {
            Log::warning("تعذر تسجيل تشغيل المهمة المجدولة {$key} في جدول scheduled_task_runs: ".$e->getMessage());
        }
    }

    /**
     * جلب آخر دورة تم فيها تنفيذ المهمة
     */
    public function getLastRunKey(string $key): ?string
    {
        $cacheKey = "scheduler_last_run_{$key}";

        return Cache::get($cacheKey);
    }
}
