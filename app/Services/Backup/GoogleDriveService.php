<?php

namespace App\Services\Backup;

use App\Exceptions\BackupException;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Stream;
use Illuminate\Support\Facades\Log;

/**
 * بوابة جوجل درايف المعتمدة على مكتبة google/apiclient (الإصدار 2.x):
 * - توليد رابط التفويض وتبادل الرمز.
 * - تجديد رمز الوصول من رمز التحديث وتنفيذ عمليات درايف.
 * - رفع متقطع (Resumable Upload) يدوي بشظايا مع تقدم قابل للتتبع وإعادة محاولة.
 * - ضمان مجلد مخصص وتطبيق سياسة الاستبقاء (الاحتفاظ بآخر N نسخة).
 */
class GoogleDriveService
{
    public const MIME_FOLDER = 'application/vnd.google-apps.folder';

    public const UPLOAD_BASE = 'https://www.googleapis.com/upload/drive/v3/files';

    public const FIELDS = 'id,name,size,webViewLink';

    protected array $config;

    protected BackupSettingsService $settings;

    protected ?Client $client = null;

    protected ?Drive $drive = null;

    public function __construct(?BackupSettingsService $settings = null)
    {
        $this->settings = $settings ?? app(BackupSettingsService::class);
        $this->config = (array) config('services.google_drive', []);
    }

    // ═══════════════ OAuth ═══════════════

    protected function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $id = (string) ($this->settings->clientId() ?? '');
        $secret = (string) ($this->settings->clientSecret() ?? '');

        if ($id === '' || $secret === '') {
            throw new BackupException(
                'بيانات تطبيق Google OAuth (Client ID / Client Secret) غير مضبوطة سواء في جدول إعدادات النظام (system_settings) أو في إعدادات الخادم (.env).'
            );
        }

        $this->client = new Client;
        $this->client->setApplicationName((string) ($this->config['application_name'] ?? 'NetSales Backup'));
        $this->client->setClientId($id);
        $this->client->setClientSecret($secret);
        $this->client->setRedirectUri($this->settings->redirectUri());
        $this->client->setScopes([(string) ($this->config['scope'] ?? 'https://www.googleapis.com/auth/drive.file')]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setApprovalPrompt('force');

        return $this->client;
    }

    protected function drive(): Drive
    {
        if ($this->drive === null) {
            $this->drive = new Drive($this->client());
        }

        return $this->drive;
    }

    public function authUrl(string $state, ?string $redirectUri = null): string
    {
        $client = $this->client();
        if ($redirectUri !== null) {
            $client->setRedirectUri($redirectUri);
        }

        return $client->createAuthUrl(null, ['state' => $state]);
    }

    public function exchangeCode(string $code, ?string $redirectUri = null): array
    {
        $client = $this->client();
        if ($redirectUri !== null) {
            $client->setRedirectUri($redirectUri);
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (! empty($token['error'])) {
            throw new BackupException('فشل تبادل رمز المصادقة مع جوجل: '.$this->tokenError($token['error']));
        }

        $refreshToken = $token['refresh_token'] ?? null;
        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new BackupException(
                'لم يحصل التطبيق على رمز تحديث (refresh token). أعد الاتصال، أو أنشئ Client ID من نوع Desktop مع السماح بالوصول المستمر.'
            );
        }

        try {
            $this->client()->setAccessToken($token);
        } catch (\Throwable $e) {
            // تجاهل
        }

        $connection = [
            'access_token' => $token['access_token'] ?? null,
            'refresh_token' => $refreshToken,
            'expires_at' => ((int) ($token['created'] ?? time())) + ((int) ($token['expires_in'] ?? 3600)),
            'account_email' => '',
            'folder_id' => null,
            'connected_at' => now('Asia/Aden')->toDateTimeString(),
        ];

        $connection['account_email'] = $this->currentUserEmail();

        return $connection;
    }

    public function authenticate(array $connection): void
    {
        $refreshToken = (string) ($connection['refresh_token'] ?? '');

        if ($refreshToken === '') {
            throw new BackupException('لا يوجد رمز تحديث لجوجل درايف — أعد ربط حساب جوجل من إعدادات النسخ الاحتياطي.');
        }

        $token = $this->client()->fetchAccessTokenWithRefreshToken($refreshToken);

        if (! empty($token['error'])) {
            throw new BackupException('فشل تجديد رمز الوصول لجوجل درايف: '.$this->tokenError($token['error']));
        }

        $this->client()->setAccessToken($token);
    }

    public function currentUserEmail(): string
    {
        try {
            $about = $this->drive()->about->get(['fields' => 'user']);
            $user = $about ? $about->getUser() : null;

            return $user ? (string) $user->getEmailAddress() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    // ═══════════════ المجلدات ═══════════════

    public function findFolder(string $name): ?string
    {
        $query = sprintf(
            "name = '%s' and mimeType = '%s' and trashed = false",
            $this->escapeQuery($name),
            self::MIME_FOLDER
        );

        $result = $this->drive()->files->listFiles([
            'q' => $query,
            'spaces' => 'drive',
            'pageSize' => 1,
            'fields' => 'files(id)',
        ]);

        $files = $result ? $result->getFiles() : [];

        return count($files) > 0 ? $files[0]->getId() : null;
    }

    public function ensureFolder(string $name): string
    {
        $existing = $this->findFolder($name);
        if ($existing !== null) {
            return $existing;
        }

        $metadata = new DriveFile;
        $metadata->setName($name);
        $metadata->setMimeType(self::MIME_FOLDER);

        $created = $this->drive()->files->create($metadata, ['fields' => 'id']);

        return (string) $created->getId();
    }

    // ═══════════════ الرفع المتقطع (Resumable) ═══════════════

    public function uploadFile(
        string $localPath,
        string $fileName,
        string $folderId,
        ?callable $onProgress = null,
        int $chunkSizeBytes = 8 * 1024 * 1024
    ): array {
        if (! is_file($localPath)) {
            throw new BackupException('ملف النسخة الاحتياطية المحلي غير موجود للرفع.');
        }

        $total = (int) filesize($localPath);
        if ($total <= 0) {
            throw new BackupException('ملف النسخة الاحتياطية المحلي فارغ (0 بايت).');
        }

        $mime = mime_content_type($localPath) ?: 'application/zip';
        $http = $this->client()->authorize();

        // 1) بدء جلسة الرفع المتقطع
        $init = $http->request('POST', self::UPLOAD_BASE.'?uploadType=resumable&fields='.self::FIELDS, [
            'headers' => [
                'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => $total,
            ],
            'json' => [
                'name' => $fileName,
                'parents' => [$folderId],
                'mimeType' => $mime,
            ],
        ]);

        $sessionUri = $init->getHeaderLine('Location');
        if ($sessionUri === '') {
            throw new BackupException('جوجل لم يمنح جلسة رفع متقطعة (Resumable Session) للملف.');
        }

        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new BackupException('تعذر فتح ملف النسخة المحلي للقراءة.');
        }

        try {
            $fileStream = new Stream($handle);
            $offset = 0;

            while ($offset < $total) {
                $chunkLength = min($chunkSizeBytes, $total - $offset);
                $end = $offset + $chunkLength - 1;
                $range = sprintf('bytes %d-%d/%d', $offset, $end, $total);

                $attempt = 0;
                while (true) {
                    $attempt++;

                    $chunkStream = new LimitStream($fileStream, $chunkLength, $offset);
                    $chunkStream->seek(0);

                    $response = $http->request('PUT', $sessionUri, [
                        'http_errors' => false,
                        'headers' => [
                            'Content-Type' => $mime,
                            'Content-Range' => $range,
                            'Content-Length' => (string) $chunkLength,
                        ],
                        'body' => $chunkStream,
                    ]);

                    $status = (int) $response->getStatusCode();

                    if ($status === 200 || $status === 201) {
                        $body = json_decode((string) $response->getBody(), true);
                        if ($onProgress !== null) {
                            $onProgress($total, $total);
                        }

                        return $this->toFileMeta($body, $fileName);
                    }

                    if ($status === 308) {
                        $offset = $end + 1;
                        if ($onProgress !== null) {
                            $onProgress(min($offset, $total), $total);
                        }
                        break;
                    }

                    if (in_array($status, [429, 500, 502, 503, 504], true)) {
                        if ($attempt >= 5) {
                            throw new BackupException(sprintf(
                                'تعذر رفع ملف النسخة إلى جوجل درايف بعد عدة محاولات (HTTP %d).',
                                $status
                            ));
                        }

                        usleep(min(4000000, 500000 * $attempt));

                        continue;
                    }

                    throw new BackupException(sprintf(
                        'فشل رفع ملف النسخة إلى جوجل درايف (HTTP %d): %s',
                        $status,
                        mb_substr((string) $response->getBody(), 0, 300)
                    ));
                }
            }
        } finally {
            fclose($handle);
        }

        throw new BackupException('لم يكتمل رفع ملف النسخة (حالة غير متوقعة).');
    }

    // ═══════════════ الاستبقاء والحذف ═══════════════

    public function listBackupFiles(string $folderId, string $prefix): array
    {
        $query = sprintf(
            "'%s' in parents and name contains '%s' and trashed = false",
            $this->escapeQuery($folderId),
            $this->escapeQuery($prefix)
        );

        $files = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $query,
                'fields' => 'nextPageToken,files(id,name,createdTime,size)',
                'orderBy' => 'createdTime',
                'pageSize' => 500,
            ];
            if ($pageToken !== null && $pageToken !== '') {
                $params['pageToken'] = $pageToken;
            }

            $result = $this->drive()->files->listFiles($params);
            $pageFiles = $result ? $result->getFiles() : [];

            foreach ($pageFiles as $file) {
                $name = (string) $file->getName();

                if (! str_starts_with($name, $prefix)) {
                    continue;
                }

                $files[] = [
                    'id' => (string) $file->getId(),
                    'name' => $name,
                    'createdTime' => $file->getCreatedTime(),
                    'size' => (int) ($file->getSize() ?? 0),
                ];
            }

            $pageToken = $result ? $result->getNextPageToken() : null;
        } while ($pageToken !== null && $pageToken !== '');

        return $files;
    }

    public function deleteFile(string $fileId): void
    {
        $this->drive()->files->delete($fileId);
    }

    public function applyRetention(string $folderId, string $prefix, int $keepCount): array
    {
        $keepCount = max(1, $keepCount);

        try {
            $files = $this->listBackupFiles($folderId, $prefix);
        } catch (\Throwable $e) {
            Log::error('تعذر سرد ملفات النسخ لتطبيق سياسة الاستبقاء: '.$e->getMessage(), [
                'folderId' => $folderId,
                'prefix' => $prefix,
            ]);

            return [
                'total_found' => 0,
                'deleted' => 0,
                'kept' => 0,
            ];
        }

        usort($files, function (array $a, array $b) {
            $aTime = strtotime((string) ($a['createdTime'] ?? '')) ?: 0;
            $bTime = strtotime((string) ($b['createdTime'] ?? '')) ?: 0;

            return $aTime <=> $bTime;
        });

        $excess = array_slice($files, 0, max(0, count($files) - $keepCount));
        $deleted = 0;

        foreach ($excess as $file) {
            try {
                $this->deleteFile($file['id']);
                $deleted++;
            } catch (\Throwable $e) {
                // فشل حذف ملف قديم لا يفشل النسخة
            }
        }

        return [
            'total_found' => count($files),
            'deleted' => $deleted,
            'kept' => count($files) - $deleted,
        ];
    }

    private function toFileMeta(array $body, string $fileName): array
    {
        $id = (string) ($body['id'] ?? '');

        if ($id === '') {
            throw new BackupException('جوجل أعاد استجابة رفع دون معرف ملف (file id).');
        }

        $link = (string) ($body['webViewLink'] ?? ('https://drive.google.com/file/d/'.$id.'/view'));

        return [
            'id' => $id,
            'name' => (string) ($body['name'] ?? $fileName),
            'size' => (int) ($body['size'] ?? 0),
            'webViewLink' => $link,
        ];
    }

    private function escapeQuery(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private function tokenError(mixed $error): string
    {
        if (is_string($error)) {
            return $error;
        }

        return is_array($error)
            ? json_encode($error, JSON_UNESCAPED_UNICODE)
            : 'خطأ غير معروف';
    }
}
