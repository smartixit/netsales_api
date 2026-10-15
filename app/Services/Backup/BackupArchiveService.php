<?php

namespace App\Services\Backup;

use App\Exceptions\BackupException;
use FilesystemIterator;
use Illuminate\Foundation\Application;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * بناء أرشيف النسخة الاحتياطية:
 * - ملف `database/dump.sql` + `database/manifest.json` + `database/manifest.sha256`.
 * - تضمين ملفات التخزين (protected/public) اختياريًا مع مراعاة حد الحجم الأقصى.
 * - إرجاع ملخص موثوق (sha256 للأرشيف + عداد الملفات والأحجام) لحفظه بسجل النسخ.
 */
class BackupArchiveService
{
    private const SKIP_DIRS = ['backups', 'logs', 'framework'];

    /**
     * @param  array  $dumpSummary  ملخص DatabaseDumpService
     *
     * @throws BackupException
     */
    public function build(
        string $zipPath,
        string $sqlPath,
        array $dumpSummary,
        bool $includeStorage,
        int $maxArchiveBytes,
        string $filePrefix
    ): array {
        if (! class_exists(ZipArchive::class)) {
            throw new BackupException('إضافة ضغط الأرشيف (ZipArchive) غير متوفرة على الخادم.');
        }

        if (! is_file($sqlPath)) {
            throw new BackupException('ملف تفريغ قاعدة البيانات غير موجود: '.$sqlPath);
        }

        $sqlBytes = (int) filesize($sqlPath);
        if ($sqlBytes > $maxArchiveBytes) {
            throw new BackupException(sprintf(
                'حجم تفريغ قاعدة البيانات (%s) أكبر من الحد الأقصى المسموح للأرشيف (%s).',
                $this->humanBytes($sqlBytes),
                $this->humanBytes($maxArchiveBytes)
            ));
        }

        $storage = $this->collectStorageFiles($includeStorage, $maxArchiveBytes - $sqlBytes);

        $targetDir = dirname($zipPath);
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }
        if (! is_writable($targetDir)) {
            throw new BackupException('مجلد أرشيف النسخ الاحتياطي غير قابل للكتابة: '.$targetDir);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('تعذر إنشاء ملف الأرشيف المضغوط للكتابة.');
        }

        try {
            $zip->addFile($sqlPath, 'database/dump.sql');

            $manifest = [
                'app' => 'NetSales',
                'backup_version' => 1,
                'generated_at' => now('Asia/Aden')->toDateTimeString(),
                'php' => PHP_VERSION,
                'laravel' => Application::VERSION,
                'database' => [
                    'name' => $dumpSummary['database'] ?? '',
                    'driver' => $dumpSummary['driver'] ?? 'mysql',
                    'consistent' => (bool) ($dumpSummary['consistent_snapshot'] ?? false),
                    'table_count' => count($dumpSummary['tables'] ?? []),
                    'total_rows' => (int) ($dumpSummary['total_rows'] ?? 0),
                    'file_size_bytes' => (int) ($dumpSummary['file_size_bytes'] ?? 0),
                    'file_sha256' => (string) ($dumpSummary['file_sha256'] ?? ''),
                    'tables' => $dumpSummary['tables'] ?? [],
                    'views' => $dumpSummary['views'] ?? [],
                ],
                'storage' => [
                    'included' => $includeStorage,
                    'files_count' => $storage['count'],
                    'bytes' => $storage['bytes'],
                    'file_list' => array_keys($storage['files']),
                ],
            ];

            foreach ($storage['files'] as $relative => $absolute) {
                $zip->addFile($absolute, $relative);
            }

            $zip->addFromString(
                'database/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $zip->addFromString('database/dump.sha256', (string) ($dumpSummary['file_sha256'] ?? ''));
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($zipPath);
            throw new BackupException('فشل إنشاء أرشيف النسخة: '.$e->getMessage(), 0, $e);
        }

        if ($zip->close() === false) {
            @unlink($zipPath);
            throw new BackupException('فشل إغلاق ملف الأرشيف المضغوط.');
        }

        $manifestSummary = [
            'app' => 'NetSales',
            'backup_version' => 1,
            'generated_at' => $manifest['generated_at'],
            'database' => $manifest['database'],
            'storage' => [
                'included' => $includeStorage,
                'files_count' => $storage['count'],
                'bytes' => $storage['bytes'],
            ],
        ];

        return [
            'archive_path' => $zipPath,
            'archive_bytes' => (int) filesize($zipPath),
            'archive_sha256' => (string) hash_file('sha256', $zipPath),
            'storage_included' => $includeStorage,
            'storage_files' => $storage['count'],
            'storage_bytes' => $storage['bytes'],
            'manifest_summary' => $manifestSummary,
        ];
    }

    /**
     * جمع ملفات التخزين (استثناء مجلدات النظام الداخلية) مع احترام حد الحجم.
     *
     * @throws BackupException عند تجاوز الحد
     */
    private function collectStorageFiles(bool $include, int $budget): array
    {
        $result = ['files' => [], 'count' => 0, 'bytes' => 0];

        if (! $include) {
            return $result;
        }

        $root = realpath(storage_path('app'));
        if ($root === false) {
            return $result;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $entry) {
            if (! $entry instanceof SplFileInfo || ! $entry->isFile()) {
                continue;
            }

            $full = $entry->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($full, strlen($root))), '/');

            if ($relative === '') {
                continue;
            }

            $firstSegment = explode('/', $relative)[0];
            if (in_array($firstSegment, self::SKIP_DIRS, true)) {
                continue;
            }

            $size = $entry->getSize();
            if ($budget < $result['bytes'] + $size) {
                throw new BackupException(sprintf(
                    'حجم ملفات التخزين مع الأرشيف يتجاوز الحد الأقصى المسموح (%s). أعد تقييم تضمين التخزين.',
                    $this->humanBytes($budget)
                ));
            }

            $result['files'][$relative] = $full;
            $result['bytes'] += $size;
            $result['count']++;
        }

        return $result;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, 2).' '.$units[$index];
    }
}
