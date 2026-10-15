<?php

namespace App\Services\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\SettingCategory;
use App\Interfaces\SystemSettingRepositoryInterface;
use App\Models\BackupJob;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * خدمة إعدادات النسخ الاحتياطي:
 * - قراءة/كتابة إعدادات النسخ (تفعيل، توقيت، استبقاء، تضمين التخزين، حد الحجم).
 * - إدارة بيانات الاتصال بحساب جوجل درايف مشفرة (refresh token) داخل النظام.
 * - إدارة حالة التشغيل الداخلية (موعد النسخة التالية، آخر نتيجة، عداد الفشل).
 */
class BackupSettingsService
{
    public const CATEGORY = SettingCategory::BACKUP;

    public const KEY_ENABLED = 'backup.enabled';

    public const KEY_SCHEDULE_TIME = 'backup.schedule_time';

    public const KEY_RETENTION_COUNT = 'backup.retention_count';

    public const KEY_INCLUDE_STORAGE = 'backup.include_storage';

    public const KEY_MAX_ARCHIVE_MB = 'backup.max_archive_mb';

    public const KEY_FAILURE_SMS = 'backup.failure_sms';

    public const KEY_DRIVE_CONNECTION = 'backup.drive.connection';

    public const KEY_RUNTIME = 'backup.runtime';

    public const MAX_DAILY_RETRIES = 10;

    public const OVERDUE_THRESHOLD_HOURS = 25;

    public const ESCALATION_FAILURE_THRESHOLD = 5;

    public const KEY_CLIENT_ID = 'google_drive_client_id';

    public const KEY_CLIENT_SECRET = 'google_drive_client_secret';

    public const KEY_FOLDER_NAME = 'google_drive_folder_name';

    public const KEY_REDIRECT_URI = 'google_drive_redirect_uri';

    public const DEFAULT_SCHEDULE_TIME = '03:00';

    public function __construct(
        protected SystemSettingRepositoryInterface $systemSettingRepository
    ) {}

    /**
     * قراءة قيمة إعداد نصية مع قيمة افتراضية عند الغياب.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->systemSettingRepository->findByKey($key);
        $value = optional($setting)->setting_value;

        return ($value === null || $value === '') ? $default : (string) $value;
    }

    /**
     * كتابة أو تحديث إعداد.
     */
    public function set(string $key, string $value, string $valueType = 'string', string $description = ''): void
    {
        $this->systemSettingRepository->updateOrCreateByKey($key, [
            'setting_value' => $value,
            'setting_value_type' => $valueType,
            'setting_category' => self::CATEGORY,
            'setting_desc' => $description,
        ]);
    }

    // ═══════════════ Typed Getters ═══════════════

    public function enabled(): bool
    {
        return $this->boolOf($this->get(self::KEY_ENABLED, '0'));
    }

    public function scheduleTime(): string
    {
        return (string) $this->get(self::KEY_SCHEDULE_TIME, self::DEFAULT_SCHEDULE_TIME);
    }

    public function retentionCount(): int
    {
        return max(1, (int) $this->get(self::KEY_RETENTION_COUNT, '30'));
    }

    public function includeStorage(): bool
    {
        return $this->boolOf($this->get(self::KEY_INCLUDE_STORAGE, '1'));
    }

    public function maxArchiveMb(): int
    {
        return max(1, (int) $this->get(self::KEY_MAX_ARCHIVE_MB, '1024'));
    }

    public function failureSmsEnabled(): bool
    {
        return $this->boolOf($this->get(self::KEY_FAILURE_SMS, '1'));
    }

    // ═══════════════ إعدادات تطبيق Google OAuth ═══════════════

    public function clientId(): ?string
    {
        $val = $this->get(self::KEY_CLIENT_ID) ?? $this->get('backup.drive.client_id');

        return ! empty($val) ? $val : config('services.google_drive.client_id');
    }

    public function clientSecret(): ?string
    {
        $val = $this->get(self::KEY_CLIENT_SECRET) ?? $this->get('backup.drive.client_secret');

        return ! empty($val) ? $val : config('services.google_drive.client_secret');
    }

    public function folderName(): string
    {
        $val = $this->get(self::KEY_FOLDER_NAME) ?? $this->get('backup.drive.folder_name');

        return ! empty($val) ? $val : (string) config('services.google_drive.folder_name', 'NetSales Backup');
    }

    public function redirectUri(): string
    {
        $val = $this->get(self::KEY_REDIRECT_URI) ?? $this->get('backup.drive.redirect_uri');

        return ! empty($val) ? $val : (string) config('services.google_drive.redirect_uri', 'http://localhost');
    }

    public function isOAuthConfigured(): bool
    {
        return ! empty($this->clientId()) && ! empty($this->clientSecret());
    }

    // ═══════════════ اتصال جوجل درايف (مشفر) ═══════════════

    public function driveConnection(): ?array
    {
        $raw = $this->get(self::KEY_DRIVE_CONNECTION, '');
        if ($raw === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($raw), true);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function saveDriveConnection(array $connection): void
    {
        $this->set(
            self::KEY_DRIVE_CONNECTION,
            Crypt::encryptString(json_encode($connection, JSON_UNESCAPED_UNICODE)),
            'string',
            'بيانات اتصال جوجل درايف المشفرة (refresh token)'
        );
    }

    public function clearDriveConnection(): void
    {
        $this->set(self::KEY_DRIVE_CONNECTION, '', 'string', 'بيانات اتصال جوجل درايف المشفرة (refresh token)');
    }

    // ═══════════════ الحالة الداخلية (Runtime) ═══════════════

    private function defaultRuntime(): array
    {
        return [
            'next_run_at' => null,
            'last_run_at' => null,
            'last_result' => null,
            'last_file_name' => null,
            'last_file_id' => null,
            'consecutive_failures' => 0,
            'last_error' => null,
            'drive_auth' => ['state' => null, 'redirect_uri' => null, 'expires_at' => null],
        ];
    }

    public function runtime(): array
    {
        $raw = $this->get(self::KEY_RUNTIME, '');
        if ($raw === '') {
            return $this->defaultRuntime();
        }

        $data = json_decode($raw, true);

        return is_array($data)
            ? array_merge($this->defaultRuntime(), $data)
            : $this->defaultRuntime();
    }

    public function setRuntime(array $runtime): void
    {
        $this->set(self::KEY_RUNTIME, json_encode($runtime, JSON_UNESCAPED_UNICODE), 'string', 'حالة النسخ الاحتياطي الداخلية (مواعيد ونتائج)');
    }

    public function updateRuntime(array $patch): array
    {
        $merged = array_merge($this->runtime(), $patch);
        $this->setRuntime($merged);

        return $merged;
    }

    public function saveDriveAuthState(string $state, ?string $redirectUri = null, int $lifetimeSeconds = 600): string
    {
        $this->updateRuntime([
            'drive_auth' => [
                'state' => $state,
                'redirect_uri' => $redirectUri,
                'expires_at' => now('Asia/Aden')->addSeconds($lifetimeSeconds)->toDateTimeString(),
            ],
        ]);

        return $state;
    }

    public function consumeDriveAuthState(string $state): ?string
    {
        $runtime = $this->runtime();
        $auth = $runtime['drive_auth'] ?? [];

        $matches = ($auth['state'] ?? null) === $state;
        $expiresAt = $auth['expires_at'] ?? null;
        $notExpired = $expiresAt === null || now('Asia/Aden')->lte(Carbon::parse($expiresAt));

        $redirectUri = $matches && $notExpired ? ($auth['redirect_uri'] ?? null) : null;

        $this->updateRuntime(['drive_auth' => ['state' => null, 'redirect_uri' => null, 'expires_at' => null]]);

        return $redirectUri;
    }

    // ═══════════════ تسجيل النتائج والتحقق من الاكتمال ═══════════════

    public function isBackupCompletedToday(?DateTimeInterface $date = null): bool
    {
        $dateStr = ($date ? Carbon::parse($date)->setTimezone('Asia/Aden') : Carbon::now('Asia/Aden'))->toDateString();
        $cacheKey = "backup_auto_last_success_date_{$dateStr}";

        if (Cache::get($cacheKey) === $dateStr) {
            return true;
        }

        if (Cache::get('backup_auto_last_success_date') === $dateStr) {
            return true;
        }

        try {
            $completed = BackupJob::where('backup_type', BackupType::AUTO->value)
                ->where('status', BackupStatus::SUCCESS->value)
                ->where(function ($q) use ($dateStr) {
                    $q->whereDate('finished_at', $dateStr)
                        ->orWhereDate('created_at', $dateStr);
                })
                ->exists();

            if ($completed) {
                Cache::put($cacheKey, $dateStr, now('Asia/Aden')->addDays(2));
                Cache::forever('backup_auto_last_success_date', $dateStr);

                return true;
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        return false;
    }

    public function markBackupCompletedToday(?DateTimeInterface $date = null): void
    {
        $dateStr = ($date ? Carbon::parse($date)->setTimezone('Asia/Aden') : Carbon::now('Asia/Aden'))->toDateString();
        Cache::put("backup_auto_last_success_date_{$dateStr}", $dateStr, now('Asia/Aden')->addDays(2));
        Cache::forever('backup_auto_last_success_date', $dateStr);
    }

    public function markRunSuccess(string $fileName, string $fileId): void
    {
        $now = Carbon::now('Asia/Aden');
        $this->markBackupCompletedToday($now);

        $this->updateRuntime([
            'last_run_at' => $now->toDateTimeString(),
            'last_result' => 'success',
            'last_file_name' => $fileName,
            'last_file_id' => $fileId,
            'consecutive_failures' => 0,
            'last_error' => null,
        ]);
    }

    public function markRunFailure(string $message): void
    {
        $now = Carbon::now('Asia/Aden');
        $runtime = $this->runtime();

        $this->updateRuntime([
            'last_run_at' => $now->toDateTimeString(),
            'last_result' => 'failed',
            'consecutive_failures' => ((int) ($runtime['consecutive_failures'] ?? 0)) + 1,
            'last_error' => mb_substr($message, 0, 2000),
        ]);
    }

    public function isDailyRetryLimitReached(): bool
    {
        $runtime = $this->runtime();
        $failures = (int) ($runtime['consecutive_failures'] ?? 0);

        return $failures >= self::MAX_DAILY_RETRIES;
    }

    public function isBackupOverdue(?DateTimeInterface $now = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $runtime = $this->runtime();
        $lastRunAt = $runtime['last_run_at'] ?? null;
        $lastResult = $runtime['last_result'] ?? null;

        if ($lastRunAt === null && $lastResult === null) {
            return false;
        }

        $referenceTime = $lastRunAt;
        if ($referenceTime === null) {
            return true;
        }

        $nowCarbon = $now ? Carbon::parse($now)->setTimezone('Asia/Aden') : Carbon::now('Asia/Aden');
        $lastRun = Carbon::parse($referenceTime)->setTimezone('Asia/Aden');
        $hoursSinceLastRun = abs((float) $nowCarbon->diffInHours($lastRun));

        return $hoursSinceLastRun >= self::OVERDUE_THRESHOLD_HOURS;
    }

    public function shouldEscalateAlert(): bool
    {
        $runtime = $this->runtime();
        $failures = (int) ($runtime['consecutive_failures'] ?? 0);

        return $failures >= self::ESCALATION_FAILURE_THRESHOLD;
    }

    public function calculateNextRunAt(?DateTimeInterface $now = null): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $nowCarbon = $now ? Carbon::parse($now)->setTimezone('Asia/Aden') : Carbon::now('Asia/Aden');
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $this->scheduleTime())), 2, 0);

        if ($this->isBackupCompletedToday($nowCarbon)) {
            return $nowCarbon->copy()->addDay()->setTime($hour, $minute, 0)->toDateTimeString();
        }

        $candidateToday = $nowCarbon->copy()->setTime($hour, $minute, 0);

        if ($candidateToday->greaterThan($nowCarbon)) {
            return $candidateToday->toDateTimeString();
        }

        return $candidateToday->toDateTimeString();
    }

    public function calculateNextRetryAt(?DateTimeInterface $now = null): string
    {
        $nowCarbon = $now ? Carbon::parse($now)->setTimezone('Asia/Aden') : Carbon::now('Asia/Aden');

        return $nowCarbon->copy()->addMinutes(15)->toDateTimeString();
    }

    private function boolOf(?string $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
