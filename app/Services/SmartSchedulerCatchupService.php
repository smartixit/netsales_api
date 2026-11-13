<?php

namespace App\Services;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Services\Backup\BackupSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * محرك الجدولة المركزي والاستدراك الذكي لجميع مهام NetSales.
 *
 * مصدر الحقيقة لدورات التنفيذ هو سجل قاعدة البيانات schedule_slot؛ ويستخدم
 * Cache فقط للتخفيف من التنافس وتصريف Queue، وليس لتحديد آخر تشغيل ناجح.
 */
class SmartSchedulerCatchupService
{
    public function __construct(
        protected ScheduledTaskRunRepositoryInterface $taskRunRepository,
        protected BackupSettingsService $backupSettings
    ) {}

    /**
     * @return array<string, array{type: ScheduledTaskType, name: string, command: string, schedule: array}>
     */
    public function getTasks(): array
    {
        $tasks = [];

        foreach (ScheduledTaskType::cases() as $type) {
            $tasks[$type->value] = [
                'type' => $type,
                'name' => $type->label(),
                'command' => $type->command(),
                'schedule' => $type->schedule(),
            ];
        }

        return $tasks;
    }

    /**
     * نقطة تشغيل خفيفة لطلبات التطبيق: تعالج أقدم دورتين مستحقتين ثم محاولة Queue واحدة.
     */
    public function runActivityTick(): array
    {
        return $this->runAllCatchupTasks(false, null, 2, true);
    }

    /**
     * يشغل الدورات المستحقة بترتيب موعدها، مع إمكانية تحديد المهمة أو فرض تشغيل يدوي.
     *
     * @return array{executed:array<string,array>,skipped:array<string,array>,server_time:string}
     */
    public function runAllCatchupTasks(
        bool $force = false,
        ?string $singleTask = null,
        int $maxTasks = PHP_INT_MAX,
        bool $drainQueue = true
    ): array {
        $now = Carbon::now('Asia/Aden');
        $executedTasks = [];
        $skippedTasks = [];
        $dueTasks = [];

        foreach ($this->getTasks() as $key => $config) {
            if ($singleTask !== null && $key !== $singleTask && $config['command'] !== $singleTask) {
                continue;
            }

            if ($key === ScheduledTaskType::BACKUP_AUTO->value && ! $this->backupSettings->enabled()) {
                $skippedTasks[$key] = [
                    'name' => $config['name'],
                    'reason' => 'disabled_by_backup_settings',
                ];

                continue;
            }

            $schedule = $config['schedule'];
            if ($force) {
                $scheduledOccurrences = [$now->copy()];
            } elseif (($schedule['kind'] ?? null) === 'twice_daily') {
                $scheduledOccurrences = [];
                foreach ($schedule['times'] ?? [] as $time) {
                    $occurrence = $this->atTime($now, (string) $time);
                    if ($occurrence->lte($now)) {
                        $scheduledOccurrences[] = $occurrence;
                    }
                }
            } else {
                $occurrence = $this->resolveScheduledOccurrence($schedule, $now);
                $scheduledOccurrences = $occurrence === null ? [] : [$occurrence];
            }

            foreach ($scheduledOccurrences as $scheduledAt) {
                $dueTasks[] = [
                    'key' => $key.'@'.$scheduledAt->format('H:i'),
                    'config' => $config,
                    'scheduled_at' => $scheduledAt,
                ];
            }
        }

        usort($dueTasks, static function (array $left, array $right): int {
            $timeComparison = $left['scheduled_at']->getTimestamp() <=> $right['scheduled_at']->getTimestamp();

            return $timeComparison !== 0
                ? $timeComparison
                : strcmp($left['key'], $right['key']);
        });

        $processedSlots = 0;

        foreach ($dueTasks as $task) {
            if ($processedSlots >= max(0, $maxTasks)) {
                break;
            }
            $key = $task['key'];
            $config = $task['config'];
            /** @var Carbon $scheduledAt */
            $scheduledAt = $task['scheduled_at'];
            $type = $config['type'];

            if (! $this->taskRunRepository->claimScheduledSlot($type, $scheduledAt)) {
                $skippedTasks[$key] = [
                    'name' => $config['name'],
                    'reason' => 'slot_already_claimed_or_completed',
                    'scheduled_for' => $scheduledAt->toDateTimeString(),
                ];

                continue;
            }

            $processedSlots++;
            $arguments = $this->commandArguments($type, $scheduledAt);

            try {
                $exitCode = Artisan::call($config['command'], $arguments);
                $output = trim(Artisan::output());
                $status = $exitCode === 0
                    ? ScheduledTaskStatus::COMPLETED
                    : ScheduledTaskStatus::FAILED;

                $this->taskRunRepository->finishScheduledSlot($type, $scheduledAt, $status, [
                    'command' => $config['command'],
                    'arguments' => $arguments,
                    'scheduled_for' => $scheduledAt->toDateTimeString(),
                    'exit_code' => $exitCode,
                    'output' => $output,
                ]);

                $executedTasks[$key] = [
                    'name' => $config['name'],
                    'command' => $config['command'],
                    'status' => $exitCode === 0 ? 'success' : 'failed',
                    'exit_code' => $exitCode,
                    'output' => $output,
                    'scheduled_for' => $scheduledAt->toDateTimeString(),
                    'executed_at' => Carbon::now('Asia/Aden')->toDateTimeString(),
                ];

                try {
                    SystemLoggerService::log(
                        module: 'scheduler',
                        action: 'catchup_task_executed',
                        newData: [
                            'task' => $config['name'],
                            'command' => $config['command'],
                            'scheduled_for' => $scheduledAt->toDateTimeString(),
                            'exit_code' => $exitCode,
                            'output' => $output,
                        ]
                    );
                } catch (Throwable $loggingException) {
                    Log::warning('تعذر تسجيل أثر تنفيذ المهمة المجدولة: '.$loggingException->getMessage());
                }
            } catch (Throwable $exception) {
                Log::error("فشل تنفيذ {$config['command']}: ".$exception->getMessage(), [
                    'scheduled_for' => $scheduledAt->toDateTimeString(),
                    'trace' => $exception->getTraceAsString(),
                ]);

                try {
                    $this->taskRunRepository->finishScheduledSlot(
                        $type,
                        $scheduledAt,
                        ScheduledTaskStatus::FAILED,
                        [
                            'command' => $config['command'],
                            'scheduled_for' => $scheduledAt->toDateTimeString(),
                            'error' => $exception->getMessage(),
                        ]
                    );
                } catch (Throwable $recordException) {
                    Log::critical('تعذر تثبيت فشل المهمة في سجل الجدولة: '.$recordException->getMessage());
                }

                $executedTasks[$key] = [
                    'name' => $config['name'],
                    'command' => $config['command'],
                    'status' => 'error',
                    'error' => $exception->getMessage(),
                    'scheduled_for' => $scheduledAt->toDateTimeString(),
                    'executed_at' => Carbon::now('Asia/Aden')->toDateTimeString(),
                ];
            }
        }

        if ($drainQueue && ($drainResult = $this->drainPendingQueueJob($now)) !== null) {
            $executedTasks['queue_worker_drain'] = $drainResult;
        }

        return [
            'executed' => $executedTasks,
            'skipped' => $skippedTasks,
            'server_time' => $now->toDateTimeString(),
        ];
    }

    private function resolveScheduledOccurrence(array $schedule, Carbon $now): ?Carbon
    {
        $kind = $schedule['kind'] ?? null;

        if ($kind === 'every_minute') {
            return $now->copy()->startOfMinute();
        }

        if ($kind === 'every_fifteen_minutes') {
            $minute = intdiv($now->minute, 15) * 15;

            return $now->copy()->setTime($now->hour, $minute, 0);
        }

        if ($kind === 'twice_daily') {
            $times = $schedule['times'] ?? [];
            sort($times);

            $todayOccurrences = [];
            foreach ($times as $time) {
                $todayOccurrences[] = $this->atTime($now, $time);
            }

            foreach (array_reverse($todayOccurrences) as $occurrence) {
                if ($occurrence->lte($now)) {
                    return $occurrence;
                }
            }

            return $this->atTime($now->copy()->subDay(), end($times) ?: '00:00');
        }

        $time = $schedule['time'] ?? null;
        if ($time === null) {
            $time = $this->backupSettings->scheduleTime();
        }

        if ($kind === 'daily_at') {
            $occurrence = $this->atTime($now, $time);

            return $occurrence->gt($now) ? $occurrence->subDay() : $occurrence;
        }

        if ($kind === 'weekly_on') {
            $day = (int) ($schedule['day'] ?? 0);
            $daysBack = ($now->dayOfWeek - $day + 7) % 7;
            $occurrence = $now->copy()->startOfDay()->subDays($daysBack);
            $occurrence = $this->atTime($occurrence, $time);

            return $occurrence->gt($now) ? $occurrence->subWeek() : $occurrence;
        }

        if ($kind === 'monthly_on') {
            $day = max(1, (int) ($schedule['day'] ?? 1));
            $monthStart = $now->copy()->startOfMonth();
            $occurrence = $this->atTime($monthStart, $time)
                ->day(min($day, $monthStart->daysInMonth));

            if ($occurrence->gt($now)) {
                $previousMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $occurrence = $this->atTime($previousMonth, $time)
                    ->day(min($day, $previousMonth->daysInMonth));
            }

            return $occurrence;
        }

        return null;
    }

    private function atTime(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $date->copy()->setTime($hour, $minute, 0);
    }

    private function commandArguments(ScheduledTaskType $type, Carbon $scheduledAt): array
    {
        return match ($type) {
            ScheduledTaskType::BACKUP_AUTO,
            ScheduledTaskType::REPORTS_DAILY_SMS => [
                '--date' => $scheduledAt->toDateString(),
            ],
            ScheduledTaskType::REPORTS_MONTHLY_SMS => [
                '--month' => $scheduledAt->format('Y-m'),
            ],
            ScheduledTaskType::ALERTS_LOW_BALANCE => [
                '--date' => $scheduledAt->toDateString(),
                '--slot' => $scheduledAt->format('H:i'),
            ],
            default => [],
        };
    }

    /**
     * يعالج عنصراً واحداً فقط في كل نبضة لتجنب تحويل طلب HTTP إلى عامل طوابير دائم.
     *
     * @return array<string, mixed>|null
     */
    protected function drainPendingQueueJob(Carbon $now): ?array
    {
        $connection = (string) config('queue.default');

        if ($connection === '' || in_array($connection, ['sync', 'null'], true)) {
            return null;
        }

        try {
            if (Queue::connection($connection)->size() < 1) {
                return null;
            }
        } catch (Throwable $exception) {
            Log::warning('تعذر فحص حجم اتصال الطابور قبل التصريف: '.$exception->getMessage(), [
                'queue_connection' => $connection,
            ]);

            return null;
        }

        try {
            $jobLock = Cache::lock('smart_catchup_queue_worker_lock', 240);
        } catch (Throwable $exception) {
            Log::warning('تعذر إنشاء قفل تصريف الطابور: '.$exception->getMessage());

            return null;
        }

        if (! $jobLock->get()) {
            return null;
        }

        try {
            $exitCode = Artisan::call('queue:work', [
                'connection' => $connection,
                '--once' => true,
                '--tries' => 3,
                '--timeout' => 60,
                '--sleep' => 0,
            ]);
            $output = trim(Artisan::output());

            SystemLoggerService::log(
                module: 'scheduler',
                action: 'queue_worker_drained',
                newData: [
                    'command' => 'queue:work '.$connection.' --once',
                    'exit_code' => $exitCode,
                    'output' => $output,
                ]
            );

            return [
                'name' => 'معالجة عنصر واحد من طابور المهام',
                'command' => 'queue:work '.$connection.' --once',
                'status' => $exitCode === 0 ? 'success' : 'failed',
                'exit_code' => $exitCode,
                'output' => $output,
                'executed_at' => $now->toDateTimeString(),
            ];
        } catch (Throwable $exception) {
            Log::warning('تعذر تصريف عنصر من الطابور: '.$exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            return null;
        } finally {
            optional($jobLock)->release();
        }
    }
}
