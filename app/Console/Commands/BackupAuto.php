<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\BackupJob;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Services\Backup\BackupOrchestratorService;
use App\Services\Backup\BackupSettingsService;
use App\Services\Support\SMSGateService;
use App\Services\SystemLoggerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * أمر النسخ الاحتياطي التلقائي المجدول الشامل للنظام (NetSales Automatic Cloud Backup).
 *
 * - فحص الاستحقاق الدقيق وفق توقيت صنعاء (Asia/Aden).
 * - دعم التعويض الذكي في حال فات موعد الأمس.
 * - حماية تامة من التكرار عبر التحقق المسبق والقفل الذري Mutex.
 * - المحاولات البينية صامتة، وإرسال تنبيهات SMS والإشعارات حصرياً للـ Super Admin
 *   فقط بعد استنفاد المحاولة الأخيرة منعاً لهدر رصيد الرسائل.
 */
class BackupAuto extends Command
{
    protected $signature = 'backup:auto 
                            {--date= : Specific date for backup (YYYY-MM-DD)}
                            {--force : Force running even if already completed today}';

    protected $description = 'ينشئ النسخة الاحتياطية التلقائية الشاملة للنظام ويرفعها إلى Google Drive مع التعويض الذكي والتنبيهات';

    public function handle(BackupSettingsService $settings, BackupOrchestratorService $orchestrator): int
    {
        $now = Carbon::now('Asia/Aden');
        $force = (bool) $this->option('force');
        $dateInput = $this->option('date');

        // 1. التحقق من تفعيل ميزة النسخ الاحتياطي التلقائي
        if (! $settings->enabled()) {
            $this->info('ℹ️ النسخ الاحتياطي التلقائي معطّل بالنظام (backup.enabled = 0) - تم التجاوز.');

            return 0;
        }

        // 2. التحقق من حد المحاولات اليومية (منع استنزاف الموارد وتكرار الرسائل)
        if (! $force && $settings->isDailyRetryLimitReached()) {
            $maxRetries = BackupSettingsService::MAX_DAILY_RETRIES;
            $errorMsg = "تم تجاوز الحد الأقصى لمحاولات النسخ الاحتياطي اليومية ({$maxRetries} محاولات). يرجى مراجعة إعدادات السيرفر أو حساب Google Drive يدوياً.";
            $this->error("❌ {$errorMsg}");

            $dateKey = $now->toDateString();
            $finalAlertKey = "backup_auto_final_failure_alert_sent_{$dateKey}";

            if (! Cache::has($finalAlertKey)) {
                Cache::put($finalAlertKey, true, now()->endOfDay());
                $this->sendFailureAlertsToSuperAdmin($settings, $errorMsg, $now);
                SystemLoggerService::error('backup_auto_daily_limit_reached', ['message' => $errorMsg]);
            }

            return 2;
        }

        // 3. التحقق من القفل الذري لمنع تداخل العمليات المتزامنة
        $lock = Cache::lock('netsales_backup_mutex', 1800);
        if (! $lock->get()) {
            $this->warn('⚠️ يوجد أمر نسخ احتياطي قيد التنفيذ حالياً - تم التجاوز.');

            return 0;
        }

        try {
            // 4. إنهاء أي وظائف منقطعة قديمة (Stale Jobs)
            $orchestrator->markStaleJobsFailed();

            // 5. التحقق من اتصال حساب Google Drive
            if ($settings->driveConnection() === null) {
                $errorMsg = 'حساب Google Drive غير متصل أو منتهي؛ يرجى إعادة ربط الحساب من إعدادات النسخ الاحتياطي.';
                $this->error("❌ {$errorMsg}");

                $dateKey = $now->toDateString();
                $alreadySentKey = "backup_drive_disconnected_alert_{$dateKey}";
                if (! Cache::has($alreadySentKey)) {
                    Cache::put($alreadySentKey, true, now()->endOfDay());
                    $this->sendFailureAlertsToSuperAdmin($settings, $errorMsg, $now);
                    SystemLoggerService::error('backup_auto_drive_disconnected', ['message' => $errorMsg]);
                }

                $settings->updateRuntime(['next_run_at' => $settings->calculateNextRetryAt($now)]);

                return 2;
            }

            // 6. تحديد التاريخ المستهدف بذكاء ودعم التعويض الفائت
            [$schedHour, $schedMinute] = array_pad(array_map('intval', explode(':', $settings->scheduleTime())), 2, 0);

            if (! empty($dateInput)) {
                $targetDate = Carbon::parse($dateInput, 'Asia/Aden');
            } else {
                if ($now->hour < $schedHour) {
                    $yesterday = $now->copy()->subDay();
                    if ($force || ! $settings->isBackupCompletedToday($yesterday)) {
                        $targetDate = $yesterday;
                        $this->info("ℹ️ جاري تعويض النسخة الاحتياطية الفائتة ليوم أمس: {$targetDate->toDateString()}");
                    } else {
                        $this->info("ℹ️ موعد النسخة الاحتياطية اليومية مضبوط على الساعة {$settings->scheduleTime()} بتوقيت صنعاء؛ ونسخة الأمس مكتملة.");

                        return 0;
                    }
                } else {
                    $targetDate = $now;
                }
            }

            // 7. التحقق مما إذا كانت النسخة قد نُفذت بنجاح بالفعل لهذا التاريخ
            if (! $force && $settings->isBackupCompletedToday($targetDate)) {
                $this->info("النسخة الاحتياطية مكتملة بالفعل لتاريخ {$targetDate->toDateString()}. استخدم --force لإعادة التنفيذ الإجباري.");

                return 0;
            }

            $this->info("🚀 جاري بدء عملية النسخ الاحتياطي الشاملة لتاريخ: {$targetDate->toDateString()}...");

            // التنفيذ الفعلي بأسلوب صامت أثناء المحاولات البينية لمنع هدر الرسائل
            $job = $orchestrator->run(BackupJob::TYPE_AUTO, null, silentOnFailure: true);

            if ($job->status === BackupJob::STATUS_SUCCESS) {
                $settings->markBackupCompletedToday($targetDate);
                $settings->updateRuntime(['next_run_at' => $settings->calculateNextRunAt($now)]);

                $this->info("✅ اكتمل النسخ الاحتياطي التلقائي بنجاح: {$job->file_name}");
                if ($job->drive_web_view_link) {
                    $this->line("   رابط Google Drive: {$job->drive_web_view_link}");
                }

                SystemLoggerService::logAddition($job, 'backup');

                return 0;
            }

            // عند الفشل: تحديث موعد إعادة المحاولة القادمة
            $settings->updateRuntime(['next_run_at' => $settings->calculateNextRetryAt($now)]);

            $errorMsg = $job->error_message ?? 'خطأ غير معروف أثناء النسخ الاحتياطي';
            $this->error("❌ فشلت محاولة النسخ الاحتياطي: {$errorMsg}");

            $runtime = $settings->runtime();
            $failures = (int) ($runtime['consecutive_failures'] ?? 0);
            $maxRetries = BackupSettingsService::MAX_DAILY_RETRIES;

            // إرسال التنبيه و SMS للـ Super Admin فقط عند فشل المحاولة الأخيرة
            if ($failures >= $maxRetries || $settings->isDailyRetryLimitReached()) {
                $dateKey = $targetDate->toDateString();
                $finalAlertKey = "backup_auto_final_failure_alert_sent_{$dateKey}";

                if (! Cache::has($finalAlertKey)) {
                    Cache::put($finalAlertKey, true, now()->endOfDay());
                    $finalReason = "تم استنفاد كافة محاولات النسخ الاحتياطي اليومية ({$maxRetries} محاولات) وفشلت العملية.\n⚠️ السبب: {$errorMsg}";
                    $this->sendFailureAlertsToSuperAdmin($settings, $finalReason, $now);
                    SystemLoggerService::error('backup_auto_final_failure', ['message' => $finalReason]);
                }
            } else {
                $this->warn("ℹ️ فشلت المحاولة ({$failures}/{$maxRetries}) — سيتم إعادة المحاولة بعد 15 دقيقة تلقائياً دون إرسال SMS.");
                SystemLoggerService::warning('backup_auto_attempt_failed', ['attempt' => $failures, 'max_retries' => $maxRetries, 'error' => $errorMsg], module: 'backup');
            }

            return 2;
        } catch (\Throwable $e) {
            $msg = 'فشل تنفيذ أمر النسخ الاحتياطي التلقائي: '.$e->getMessage();
            $this->error($msg);
            SystemLoggerService::error('backup_auto_exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            $settings->updateRuntime(['next_run_at' => $settings->calculateNextRetryAt($now)]);

            $runtime = $settings->runtime();
            $failures = (int) ($runtime['consecutive_failures'] ?? 0);
            $maxRetries = BackupSettingsService::MAX_DAILY_RETRIES;

            if ($failures >= $maxRetries || $settings->isDailyRetryLimitReached()) {
                $this->sendFailureAlertsToSuperAdmin($settings, $e->getMessage(), $now);
            }

            return 2;
        } finally {
            $lock->release();
        }
    }

    /**
     * إرسال رسالة SMS وإشعار داخلي حصرياً للـ Super Admin فقط عند الفشل النهائي
     */
    protected function sendFailureAlertsToSuperAdmin(BackupSettingsService $settings, string $reason, Carbon $now): void
    {
        try {
            $roleIds = Role::whereIn('role_name', ['Super Admin', 'super_admin', 'Admin'])
                ->orWhere('role_id', 1)
                ->pluck('role_id')
                ->all();

            $superAdmins = User::where('status', 'ACTIVE')
                ->whereIn('role_id', $roleIds)
                ->get(['user_id', 'user_phone_number', 'user_full_name']);

            if ($superAdmins->isEmpty()) {
                return;
            }

            // 1. إنشاء إشعار داخلي في التطبيق
            foreach ($superAdmins as $admin) {
                Notification::create([
                    'user_id' => $admin->user_id,
                    'notification_type' => NotificationType::WARNING,
                    'notification_title' => '🚨 تنبيه: فشل النسخ الاحتياطي التلقائي',
                    'notification_message' => $reason,
                    'is_read' => false,
                ]);
            }

            // 2. إرسال SMS للـ Super Admin فقط
            if ($settings->failureSmsEnabled()) {
                $phoneNumbers = $superAdmins->pluck('user_phone_number')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (! empty($phoneNumbers)) {
                    $nowStr = $now->format('Y-m-d h:i A');
                    $smsMessage = "🚨 NetSales | تنبيه نهائي للنسخ الاحتياطي:\n{$reason}\n⏰ التوقيت: {$nowStr}";

                    $smsGate = app(SMSGateService::class);
                    $smsGate->sendSms(
                        phoneNumbers: $phoneNumbers,
                        message: $smsMessage,
                        priority: 1,
                        messageType: 'admin_broadcast'
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('تعذر إرسال تنبيه فشل النسخة الاحتياطية للـ Super Admin: '.$e->getMessage());
        }
    }
}
