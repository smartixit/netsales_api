<?php

namespace App\Services;

use App\Enums\ScheduledTaskStatus;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Interfaces\SystemSettingRepositoryInterface;
use App\Models\ScheduledTaskRun;
use App\Services\Backup\BackupSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * محرك التعويض الذكي للمهام المجدولة (Smart Scheduler Catch-up Engine).
 *
 * يحل مشكلة تفويت المهام المجدولة عند غياب الـ OS Crontab المستمر أو عند إعادة تشغيل السيرفر.
 * يفحص استحقاق المهام (ساعية، يومية، أسبوعية، شهرية) وينفذ المهام الفائتة فوراً
 * مع حماية تامة من التكرار والتداخل (Anti-Overlapping) وتسجيل السجلات في scheduled_task_runs.
 */
class SmartSchedulerCatchupService
{
    /**
     * سجل كل المهام المجدولة في نظام NetSales وقواعد استحقاقها
     */
    protected array $tasks = [
        'subscriptions_expire_outdated' => [
            'name' => 'فحص وإنهاء اشتراكات الشبكات المنتهية',
            'command' => 'subscriptions:expire-outdated',
            'frequency' => 'hourly',
            'target_hour' => 0,
        ],
        'subscriptions_notify_expiring' => [
            'name' => 'تنبيه باقتراب انتهاء اشتراكات الشبكات',
            'command' => 'subscriptions:notify-expiring',
            'frequency' => 'daily',
            'target_hour' => 10,
        ],
        'reports_daily_sms' => [
            'name' => 'إرسال الملخص المالي اليومي للشبكات (SMS)',
            'command' => 'reports:daily-summary-sms',
            'frequency' => 'daily',
            'target_hour' => 20,
        ],
        'reports_monthly_sms' => [
            'name' => 'إرسال الملخص المالي الشهري للشبكات (SMS)',
            'command' => 'reports:monthly-summary-sms',
            'frequency' => 'monthly',
            'target_hour' => 20,
        ],
        'alerts_low_balance' => [
            'name' => 'فحص وتنبيه انخفاض أرصدة محافظ نقاط البيع',
            'command' => 'alerts:low-balance',
            'frequency' => 'daily',
            'target_hour' => 12,
        ],
        'backup_auto' => [
            'name' => 'النسخ الاحتياطي التلقائي الشامل للنظام (Google Drive)',
            'command' => 'backup:auto',
            'frequency' => 'daily',
            'target_hour' => 3,
        ],
        'cleanup_user_sessions' => [
            'name' => 'تنظيف الجلسات المنتهية والمبطلة',
            'command' => 'cleanup:user-sessions',
            'frequency' => 'daily',
            'target_hour' => 2,
        ],
        'cleanup_expired_otp' => [
            'name' => 'تنظيف رموز التحقق المؤقتة (OTP) المنتهية أو المستعملة',
            'command' => 'cleanup:expired-otp',
            'frequency' => 'daily',
            'target_hour' => 2,
        ],
        'security_lift_bans' => [
            'name' => 'رفع الحظر الأمني المنتهي تلقائياً',
            'command' => 'security:lift-expired-bans',
            'frequency' => 'daily',
            'target_hour' => 2,
        ],
        'system_prune_logs' => [
            'name' => 'أرشفة وتنظيف سجلات النظام القديمة',
            'command' => 'system:prune-logs',
            'frequency' => 'weekly',
            'target_hour' => 4,
        ],
    ];

    public function __construct(
        protected ScheduledTaskRunRepositoryInterface $taskRunRepository,
        protected SystemSettingRepositoryInterface $systemSettingRepository,
        protected BackupSettingsService $backupSettings
    ) {}

    /**
     * تشغيل فحص التعويض لكل المهام المجدولة
     */
    public function runAllCatchupTasks(bool $force = false, ?string $singleTask = null): array
    {
        $now = Carbon::now('Asia/Aden');
        $executedTasks = [];
        $skippedTasks = [];

        foreach ($this->tasks as $key => $config) {
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

        return [
            'executed' => $executedTasks,
            'skipped' => $skippedTasks,
            'server_time' => $now->toDateTimeString(),
        ];
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
                taskType: $key,
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
