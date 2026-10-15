<?php

namespace App\Http\Controllers\API\V1;

use App\Exceptions\BackupException;
use App\Http\Requests\API\V1\Backup\DriveTokenRequest;
use App\Http\Requests\API\V1\Backup\UpdateBackupConfigRequest;
use App\Http\Resources\API\V1\Backup\BackupJobResource;
use App\Interfaces\BackupJobRepositoryInterface;
use App\Models\BackupJob;
use App\Services\Backup\BackupOrchestratorService;
use App\Services\Backup\BackupSettingsService;
use App\Services\Backup\GoogleDriveService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة النسخ الاحتياطي لقاعدة البيانات ورفعها إلى جوجل درايف (Strict Lean Controller).
 */
#[OA\Tag(name: 'Backup', description: 'إدارة النسخ الاحتياطي السحابي الشامل للنظام وتكامل Google Drive')]
class BackupController extends BaseController
{
    public function __construct(
        protected BackupSettingsService $settings,
        protected BackupOrchestratorService $orchestrator,
        protected GoogleDriveService $drive,
        protected BackupJobRepositoryInterface $backupJobRepository
    ) {}

    /**
     * حالة وإعدادات النسخ الاحتياطي
     */
    public function settings(): JsonResponse
    {
        try {
            $connection = $this->settings->driveConnection();
            $runtime = $this->settings->runtime();
            $lastJob = $this->backupJobRepository->getLatestSuccessful();
            $nextRunAt = $this->settings->calculateNextRunAt();
            $isTodayCompleted = $this->settings->isBackupCompletedToday();

            return $this->successResponse('بيانات إعدادات النسخ الاحتياطي', [
                'connected' => $connection !== null,
                'account_email' => $connection['account_email'] ?? null,
                'folder_id' => $connection['folder_id'] ?? null,
                'enabled' => $this->settings->enabled(),
                'schedule_time' => $this->settings->scheduleTime(),
                'retention_count' => $this->settings->retentionCount(),
                'include_storage' => $this->settings->includeStorage(),
                'max_archive_mb' => $this->settings->maxArchiveMb(),
                'failure_sms' => $this->settings->failureSmsEnabled(),
                'can_run' => $connection !== null,
                'is_today_completed' => $isTodayCompleted,
                'next_run_at' => $nextRunAt,
                'last_run_at' => $runtime['last_run_at'],
                'last_result' => $runtime['last_result'],
                'last_error' => $runtime['last_error'],
                'consecutive_failures' => $runtime['consecutive_failures'],
                'is_overdue' => $this->settings->isBackupOverdue(),
                'is_daily_limit_reached' => $this->settings->isDailyRetryLimitReached(),
                'max_daily_retries' => BackupSettingsService::MAX_DAILY_RETRIES,
                'last_job' => $lastJob ? new BackupJobResource($lastJob) : null,
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * فحص صحة بيئة النسخ الاحتياطي على الخادم
     */
    public function health(): JsonResponse
    {
        try {
            $dir = storage_path('app/backups');
            $isWritable = is_dir($dir) ? is_writable($dir) : @mkdir($dir, 0775, true);
            $diskFreeBytes = @disk_free_space($dir);
            $diskFreeMb = $diskFreeBytes !== false ? round($diskFreeBytes / (1024 * 1024), 2) : null;
            $connected = $this->settings->driveConnection() !== null;
            $runtime = $this->settings->runtime();
            $isOverdue = $this->settings->isBackupOverdue();
            $retryLimitReached = $this->settings->isDailyRetryLimitReached();

            $overallStatus = ($isWritable && class_exists('ZipArchive') && $connected && ! $isOverdue && ! $retryLimitReached)
                ? 'healthy'
                : ($connected ? 'warning' : 'disconnected');

            return $this->successResponse('فحص صحة النسخ الاحتياطي', [
                'status' => $overallStatus,
                'backups_dir_writable' => (bool) $isWritable,
                'disk_free_mb' => $diskFreeMb,
                'zip_enabled' => class_exists('ZipArchive'),
                'oauth_configured' => $this->settings->isOAuthConfigured(),
                'drive_connected' => $connected,
                'is_overdue' => $isOverdue,
                'retry_limit_reached' => $retryLimitReached,
                'consecutive_failures' => (int) ($runtime['consecutive_failures'] ?? 0),
                'max_daily_retries' => BackupSettingsService::MAX_DAILY_RETRIES,
                'database' => config('database.default'),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * بدء المصادقة مع جوجل: توليد رابط التفويض
     */
    public function driveAuth(Request $request): JsonResponse
    {
        try {
            $redirectUri = (string) $request->input('redirect_uri', '');

            if ($redirectUri !== '') {
                $parts = parse_url($redirectUri);
                $isLoopback = is_array($parts)
                    && ($parts['scheme'] ?? '') === 'http'
                    && in_array(strtolower(trim((string) ($parts['host'] ?? ''), '[]')), ['localhost', '127.0.0.1'], true);

                if (! $isLoopback) {
                    return $this->errorResponse(
                        'redirect_uri يجب أن يكون عنوان Loopback محلي (http://localhost أو http://127.0.0.1).',
                        [],
                        422
                    );
                }
            }

            $state = Str::random(40);
            $this->settings->saveDriveAuthState($state, $redirectUri !== '' ? $redirectUri : null);

            return $this->successResponse('افتح الرابط في المتصفح، ثم سمح بالوصول ليكتمل الربط تلقائياً', [
                'auth_url' => $this->drive->authUrl($state, $redirectUri !== '' ? $redirectUri : null),
                'state' => $state,
                'redirect_uri' => $redirectUri,
                'expires_at' => $this->settings->runtime()['drive_auth']['expires_at'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إتمام المصادقة: تبادل الرمز وحفظ بيانات الاتصال مشفرة
     */
    public function driveToken(DriveTokenRequest $request): JsonResponse
    {
        try {
            $redirectUri = $this->settings->consumeDriveAuthState($request->getState());
            if ($redirectUri === null) {
                return $this->errorResponse('رمز المصادقة (state) منتهي أو غير مطابق — أعد بدء الاتصال من جديد.', [], 422);
            }

            $connection = $this->drive->exchangeCode($request->getCode(), $redirectUri);
            $this->settings->saveDriveConnection($connection);

            return $this->successResponse('تم ربط حساب جوجل درايف بنجاح', [
                'connected' => true,
                'account_email' => $connection['account_email'],
                'folder_id' => $connection['folder_id'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 400);
        }
    }

    /**
     * فصل حساب جوجل درايف
     */
    public function driveDisconnect(): JsonResponse
    {
        try {
            $this->settings->clearDriveConnection();
            $this->settings->updateRuntime(['last_error' => null, 'last_result' => null]);

            return $this->successResponse('تم فصل حساب جوجل درايف بنجاح');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث إعدادات النسخ الاحتياطي
     */
    public function updateConfig(UpdateBackupConfigRequest $request): JsonResponse
    {
        try {
            $data = $request->getConfigData();

            if (array_key_exists('enabled', $data) && $data['enabled'] !== null) {
                $this->settings->set(BackupSettingsService::KEY_ENABLED, filter_var($data['enabled'], FILTER_VALIDATE_BOOL) ? '1' : '0', 'boolean', 'تفعيل النسخ الاحتياطي التلقائي المجدول');
            }
            if (array_key_exists('schedule_time', $data) && $data['schedule_time'] !== null) {
                $this->settings->set(BackupSettingsService::KEY_SCHEDULE_TIME, (string) $data['schedule_time'], 'string', 'توقيت النسخة التلقائية (HH:MM)');
            }
            if (array_key_exists('retention_count', $data) && $data['retention_count'] !== null) {
                $this->settings->set(BackupSettingsService::KEY_RETENTION_COUNT, (string) (int) $data['retention_count'], 'integer', 'الاحتفاظ بآخر هذا العدد من النسخ في درايف');
            }
            if (array_key_exists('include_storage', $data) && $data['include_storage'] !== null) {
                $this->settings->set(BackupSettingsService::KEY_INCLUDE_STORAGE, filter_var($data['include_storage'], FILTER_VALIDATE_BOOL) ? '1' : '0', 'boolean', 'تضمين ملفات التخزين داخل الأرشيف');
            }
            if (array_key_exists('failure_sms', $data) && $data['failure_sms'] !== null) {
                $this->settings->set(BackupSettingsService::KEY_FAILURE_SMS, filter_var($data['failure_sms'], FILTER_VALIDATE_BOOL) ? '1' : '0', 'boolean', 'إشعار SMS عند فشل النسخة التلقائية');
            }
            if (array_key_exists('client_id', $data) && $data['client_id'] !== null) {
                $this->settings->set(BackupSettingsService::KEY_CLIENT_ID, (string) $data['client_id'], 'string', 'Google Drive OAuth Client ID');
            }
            if (array_key_exists('client_secret', $data) && $data['client_secret'] !== null) {
                $this->settings->set(BackupSettingsService::KEY_CLIENT_SECRET, (string) $data['client_secret'], 'string', 'Google Drive OAuth Client Secret');
            }
            if (array_key_exists('folder_name', $data) && $data['folder_name'] !== null) {
                $this->settings->set(BackupSettingsService::KEY_FOLDER_NAME, (string) $data['folder_name'], 'string', 'Google Drive Backup Folder Name');
            }

            $this->settings->updateRuntime([
                'next_run_at' => $this->settings->calculateNextRunAt(),
            ]);

            return $this->settings();
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء نسخة احتياطية يدوياً الآن
     */
    public function run(Request $request): JsonResponse
    {
        try {
            $userId = (int) ($request->user()?->user_id ?? 1);
            $job = $this->orchestrator->run(BackupJob::TYPE_MANUAL, $userId);

            $success = $job->status === BackupJob::STATUS_SUCCESS;

            return $this->apiResponse(
                $success,
                $success ? 'تم إنشاء النسخة الاحتياطية ورفعها إلى جوجل درايف بنجاح' : 'تعذر إتمام النسخة الاحتياطية',
                new BackupJobResource($job),
                [],
                $success ? 200 : 500
            );
        } catch (BackupException $e) {
            return $this->errorResponse($e->getMessage(), [], 409);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر بدء النسخة الاحتياطية: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * سجل النسخ الاحتياطية
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'backup_type', 'created_by', 'per_page', 'all']);
            $jobs = $this->backupJobRepository->getFiltered($filters, ['creator']);

            return $this->successResponse('سجل النسخ الاحتياطية', BackupJobResource::collection($jobs));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تفاصيل نسخة محددة
     */
    public function show(int $id): JsonResponse
    {
        try {
            $job = $this->backupJobRepository->findById($id);
            if (! $job) {
                return $this->errorResponse('عذراً، النسخة الاحتياطية المطلوبة غير موجودة.', [], 404);
            }

            return $this->successResponse('تفاصيل النسخة الاحتياطية', new BackupJobResource($job));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }
}
