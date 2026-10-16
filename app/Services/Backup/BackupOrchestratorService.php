<?php

namespace App\Services\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\NotificationType;
use App\Enums\RoleKey;
use App\Exceptions\BackupException;
use App\Interfaces\BackupJobRepositoryInterface;
use App\Models\BackupJob;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Support\SMSGateService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * منسّق النسخ الاحتياطي المركزي (Enterprise Backup Orchestrator):
 * ينفّذ دورة حياة النسخة السحابية الشاملة لكامل النظام:
 * (تفريغ DB ← ضغط الأرشيف ← رفع تدفقي إلى Google Drive ← تطبيق سياسة الاستبقاء)
 * ويسجّل النتيجة في جدول backup_jobs مع معالجة الوظائف الميتة (Stale Jobs) والإشعار الفوري.
 */
class BackupOrchestratorService
{
    public const BACKUP_FOLDER_NAME = 'NetSales Backup';

    public const BACKUP_FILE_PREFIX = 'netsales-backup-';

    public const STALE_JOB_TIMEOUT_SECONDS = 3600;

    public function __construct(
        protected BackupJobRepositoryInterface $backupJobRepository,
        protected BackupSettingsService $settings,
        protected DatabaseDumpService $dumper,
        protected BackupArchiveService $archiver,
        protected GoogleDriveService $drive,
        protected SMSGateService $smsGate,
        protected ?NotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(NotificationService::class);
    }

    /**
     * تنفيذ نسخة احتياطية كاملة وإرجاع سجل الوظيفة النهائي.
     *
     * @param  string  $type  BackupJob::MANUAL أو AUTO
     * @param  int|null  $userId  من يطلب النسخة
     * @param  bool  $silentOnFailure  إخفاء الإشعار الفوري
     *
     * @throws BackupException
     */
    public function run(
        string $type = BackupType::MANUAL,
        ?int $userId = null,
        bool $silentOnFailure = false
    ): BackupJob {
        $connection = $this->settings->driveConnection();
        if ($connection === null) {
            throw new BackupException('حساب جوجل درايف غير متصل — اربط الحساب أولاً من إعدادات النسخ الاحتياطي.');
        }

        $this->markStaleJobsFailed();
        $this->assertNoActiveJob();

        // فحص مبكر لصلاحية الاعتماد قبل بدء أي تفريغ لقاعدة البيانات
        $this->drive->authenticate($connection);

        /** @var BackupJob $job */
        $job = $this->backupJobRepository->create([
            'job_uuid' => Str::uuid()->toString(),
            'backup_type' => $type,
            'status' => BackupStatus::RUNNING->value,
            'stage' => 'dump',
            'created_by' => $userId,
            'started_at' => Carbon::now('Asia/Aden'),
        ]);

        $dir = $this->resolveBackupTempDirectory();

        $sqlPath = $dir.'/backup_'.$job->job_uuid.'.sql';
        $zipPath = $dir.'/backup_'.$job->job_uuid.'.zip';

        try {
            $this->touch($job, ['stage' => 'dump']);
            $summary = $this->dumper->dump($sqlPath);

            $this->touch($job, ['stage' => 'archive']);
            $archive = $this->archiver->build(
                $zipPath,
                $sqlPath,
                $summary,
                $this->settings->includeStorage(),
                $this->settings->maxArchiveMb() * 1024 * 1024,
                self::BACKUP_FILE_PREFIX
            );

            $this->touch($job, [
                'stage' => 'upload',
                'file_size_bytes' => $archive['archive_bytes'],
                'archive_sha256' => $archive['archive_sha256'],
                'manifest_summary' => $archive['manifest_summary'],
            ]);

            $folderId = $connection['folder_id'] ?? null;
            if (! $folderId) {
                $folderId = $this->drive->ensureFolder($this->settings->folderName());
                $connection['folder_id'] = $folderId;
                $this->settings->saveDriveConnection($connection);
            }

            $fileName = self::BACKUP_FILE_PREFIX.Carbon::now('Asia/Aden')->format('Y-m-d-His').'.zip';

            $completed = $this->drive->uploadFile(
                $zipPath,
                $fileName,
                $folderId,
                function (int $done, int $total) use ($job) {
                    $this->touch($job, [
                        'stage' => 'upload',
                        'extra' => ['upload_progress' => ['done' => $done, 'total' => $total]],
                    ]);
                }
            );

            $this->touch($job, [
                'stage' => 'retention',
                'drive_file_id' => $completed['id'],
                'drive_web_view_link' => $completed['webViewLink'],
                'drive_folder_id' => $folderId,
                'file_name' => $fileName,
            ]);

            $retention = $this->drive->applyRetention(
                $folderId,
                self::BACKUP_FILE_PREFIX,
                $this->settings->retentionCount()
            );

            $finishedAt = Carbon::now('Asia/Aden');
            $this->touch($job, [
                'status' => BackupStatus::SUCCESS->value,
                'stage' => 'finalize',
                'finished_at' => $finishedAt,
                'duration_ms' => $this->durationMs($job, $finishedAt),
                'extra' => ['retention' => $retention, 'upload' => $completed],
            ]);

            $this->settings->markRunSuccess($fileName, $completed['id']);

            if ($type === BackupType::AUTO->value) {
                $this->notifyAdminsSuccess($fileName, $archive['archive_bytes'] ?? 0);
            }

        } catch (Throwable $e) {
            $failedAt = Carbon::now('Asia/Aden');
            $message = $this->friendlyMessage($e);

            $this->touch($job, [
                'status' => BackupStatus::FAILED->value,
                'finished_at' => $failedAt,
                'duration_ms' => $this->durationMs($job, $failedAt),
                'error_message' => mb_substr($message, 0, 4000),
            ]);

            $this->settings->markRunFailure($message);

            if (! $silentOnFailure) {
                $this->notifyAdmins('فشل إنشاء النسخة الاحتياطية', $message, $type);
            }

            Log::error('فشل النسخ الاحتياطي السحابي: '.$message, [
                'exception' => $e->getMessage(),
                'user_id' => $userId,
            ]);
        } finally {
            @unlink($sqlPath);
            @unlink($zipPath);
        }

        return $job->fresh();
    }

    /**
     * منع تكرار النسخ المتزامنة: وظيفة واحدة نشطة فقط.
     */
    public function assertNoActiveJob(): void
    {
        $active = BackupJob::whereIn('status', [BackupStatus::QUEUED->value, BackupStatus::RUNNING->value])->exists();

        if ($active) {
            throw new BackupException('توجد نسخة احتياطية قيد التنفيذ حالياً — انتظر اكتمالها ثم أعد المحاولة.');
        }
    }

    /**
     * إنهاء الوظائف المعلّقة المنقطعة ووصفها بالفشل.
     */
    public function markStaleJobsFailed(int $maxAgeSeconds = self::STALE_JOB_TIMEOUT_SECONDS): int
    {
        $threshold = now('Asia/Aden')->subSeconds($maxAgeSeconds);

        return BackupJob::whereIn('status', [BackupStatus::QUEUED->value, BackupStatus::RUNNING->value])
            ->where('started_at', '<', $threshold)
            ->update([
                'status' => BackupStatus::FAILED->value,
                'finished_at' => now('Asia/Aden'),
                'error_message' => 'انقطع تنفيذ النسخة قبل اكتمالها (تجاوز مهلة التشغيل).',
            ]);
    }

    protected function touch(BackupJob $job, array $data): void
    {
        $job->update($data);
    }

    protected function durationMs(BackupJob $job, CarbonInterface $finishedAt): int
    {
        if ($job->started_at === null) {
            return 0;
        }

        return (int) max(0, $job->started_at->diffInMilliseconds($finishedAt));
    }

    protected function friendlyMessage(Throwable $e): string
    {
        if ($e instanceof BackupException) {
            return $e->getMessage();
        }

        if ($e instanceof GoogleServiceException) {
            $errors = $e->getErrors();
            $reason = null;
            if (is_array($errors) && ! empty($errors)) {
                $reason = $errors[0]['message'] ?? null;
            }
            $reason = $reason ?: $e->getMessage();

            return 'خطأ من جوجل درايف: '.$reason;
        }

        return 'تعذر إتمام النسخة الاحتياطية: '.$e->getMessage();
    }

    public function resolveBackupTempDirectory(): string
    {
        $candidates = [
            storage_path('app/backups'),
            storage_path('app/temp'),
            storage_path('framework/cache/backups'),
        ];

        foreach ($candidates as $dir) {
            $dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                putenv("TMPDIR={$dir}");
                putenv("TEMP={$dir}");
                putenv("TMP={$dir}");
                $_ENV['TMPDIR'] = $dir;
                $_ENV['TEMP'] = $dir;
                $_ENV['TMP'] = $dir;

                return $dir;
            }
        }

        $sysTemp = sys_get_temp_dir();
        if (is_dir($sysTemp) && is_writable($sysTemp)) {
            return $sysTemp;
        }

        $appStorage = storage_path('app');
        if (is_dir($appStorage) && is_writable($appStorage)) {
            @ini_set('sys_temp_dir', $appStorage);

            return $appStorage;
        }

        throw new BackupException('تعذر العثور على مجلد مؤقت صالح وقابل للكتابة لإنشاء ملفات النسخ الاحتياطي.');
    }

    protected function notifyAdmins(string $title, string $body, string $type): void
    {
        try {
            $roleIds = Role::where('role_name', RoleKey::SUPER_ADMIN->value)
                ->orWhere('role_id', 1)
                ->pluck('role_id')
                ->all();

            $superAdmins = User::whereIn('role_id', $roleIds)
                ->get(['user_id', 'user_phone_number', 'user_full_name']);

            if ($this->settings->shouldEscalateAlert()) {
                $runtime = $this->settings->runtime();
                $failures = (int) ($runtime['consecutive_failures'] ?? 0);
                $title = "🚨 تنبيه تصعيدي: تكرر فشل النسخ الاحتياطي ({$failures} مرات)";
            }

            foreach ($superAdmins as $admin) {
                $this->notificationService->sendDirect(
                    userId: (int) $admin->user_id,
                    type: NotificationType::SYSTEM,
                    push: true,
                    title: $title,
                    message: "النوع: {$type}\n{$body}"
                );
            }

            if ($this->settings->failureSmsEnabled() && $type === BackupType::AUTO->value) {
                $phoneNumbers = $superAdmins->pluck('user_phone_number')->filter()->unique()->values()->all();
                if (! empty($phoneNumbers)) {
                    $nowStr = Carbon::now('Asia/Aden')->format('Y-m-d h:i A');
                    $runtime = $this->settings->runtime();
                    $failures = (int) ($runtime['consecutive_failures'] ?? 0);
                    $escalatePrefix = $failures >= BackupSettingsService::ESCALATION_FAILURE_THRESHOLD
                        ? "🚨 تنبيه تصعيدي (تكرر الفشل {$failures} مرات):\n"
                        : "🚨 NetSales | تنبيه عاجل:\n";
                    $smsMessage = "{$escalatePrefix}فشل إنشاء النسخة الاحتياطية التلقائية إلى Google Drive.\n⚠️ السبب: {$body}\n⏰ التوقيت: {$nowStr}";

                    try {
                        $this->smsGate->sendSms(
                            phoneNumbers: $phoneNumbers,
                            message: $smsMessage,
                            priority: 1,
                            recipientId: null,
                            bypassPolicy: true,
                            messageType: 'admin_broadcast'
                        );
                    } catch (Throwable $se) {
                        Log::error('SMS failure alert error: '.$se->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('تعذر إرسال إشعار فشل النسخة الاحتياطية: '.$e->getMessage());
        }
    }

    protected function notifyAdminsSuccess(string $fileName, int $sizeBytes): void
    {
        try {
            $roleIds = Role::where('role_name', RoleKey::SUPER_ADMIN->value)
                ->orWhere('role_id', 1)
                ->pluck('role_id')
                ->all();

            $superAdmins = User::whereIn('role_id', $roleIds)
                ->get(['user_id']);

            $sizeMb = round($sizeBytes / (1024 * 1024), 2);
            $sizeFormatted = $sizeMb > 0 ? " ({$sizeMb} MB)" : '';

            foreach ($superAdmins as $admin) {
                $this->notificationService->sendDirect(
                    userId: (int) $admin->user_id,
                    type: NotificationType::SYSTEM,
                    push: true,
                    title: '✅ تم إنشاء النسخة الاحتياطية بنجاح',
                    message: "تم حفظ النسخة الاحتياطية بنجاح على Google Drive.\nالملف: {$fileName}{$sizeFormatted}"
                );
            }
        } catch (Throwable $e) {
            Log::warning('تعذر إرسال إشعار نجاح النسخة الاحتياطية: '.$e->getMessage());
        }
    }
}
