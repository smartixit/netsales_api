<?php

namespace App\Services\Support;

use App\Models\Center;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * خدمة التخزين الهيكلي وإدارة مسارات وملفات النظام المحمية (Enterprise Storage Service).
 *
 * تعتمد كلياً على القرص المحمي (protected disk) بالتكامل مع FileUploadService:
 * 1. منع التخزين في المجلد العام (Public) لحماية خصوصية بيانات الطلاب والمراكز.
 * 2. دعم العزل المنطقي والفيزيائي لمجلدات المراكز والمستخدمين داخل القرص المحمي.
 * 3. توليد روابط وصول آمنة ومحمية عبر مسار الملفات المعتمد (FileController).
 * 4. إدارة دورة حياة الملفات (توليد، استبدال، حذف، إفراغ مجلدات).
 */
class StorageService
{
    /**
     * القرص الأساسي الحصري لجميع ملفات النظام (محمي بالكامل).
     */
    protected string $protectedDisk = 'protected';

    /**
     * مصفوفة إعدادات أنواع الملفات والوجهات المحمية في النظام.
     *
     * @var array<string, array{folder: string, prefix: string, disk: string, single: bool}>
     */
    protected array $typeConfig = [
        'center_logo' => [
            'folder' => 'centers/%s/logos',
            'prefix' => 'center_logo',
            'disk' => 'protected',
            'single' => true,
        ],
        'user_avatar' => [
            'folder' => 'users/%s/avatar',
            'prefix' => 'user_avatar',
            'disk' => 'protected',
            'single' => true,
        ],
        'result_template_header' => [
            'folder' => 'templates/headers',
            'prefix' => 'header',
            'disk' => 'protected',
            'single' => true,
        ],
        'result_template_footer' => [
            'folder' => 'templates/footers',
            'prefix' => 'footer',
            'disk' => 'protected',
            'single' => true,
        ],
        'notification_image' => [
            'folder' => 'notifications/%s',
            'prefix' => 'notif',
            'disk' => 'protected',
            'single' => true,
        ],
    ];

    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * إنشاء شجرة المجلدات الأساسية لمركز جديد داخل القرص المحمي حصراً.
     */
    public function createCenterDirectories(Center|string $center): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $centerCode = $center instanceof Center ? $center->center_code : $center;
        $base = 'centers/'.Str::slug($centerCode);

        $directories = [
            $base,
            $base.'/logos',
        ];

        foreach ($directories as $dir) {
            Storage::disk($this->protectedDisk)->makeDirectory($dir);
        }
    }

    /**
     * إزالة مجلدات المركز بالكامل من القرص المحمي عند الحذف النهائي.
     */
    public function deleteCenterDirectories(Center|string $center): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $centerCode = $center instanceof Center ? $center->center_code : $center;
        $path = 'centers/'.Str::slug($centerCode);

        if (Storage::disk($this->protectedDisk)->exists($path)) {
            Storage::disk($this->protectedDisk)->deleteDirectory($path);
        }
    }

    /**
     * حفظ وتصنيف ملف مرفوع داخل القرص المحمي.
     */
    public function storeFile(
        UploadedFile $file,
        string $type,
        string|int $entityIdentifier,
        ?string $customCode = null
    ): string {
        $config = $this->typeConfig[$type] ?? null;

        if (! $config) {
            throw new InvalidArgumentException("نوع التخزين غير مدعوم: [{$type}].");
        }

        $folder = sprintf($config['folder'], (string) $entityIdentifier);
        $disk = $this->protectedDisk;
        $prefix = $config['prefix'];

        // في حال كان النوع يتطلب ملفاً وحيداً، نقوم بتنظيف أي ملف سابق لنفس البادئة
        if ($config['single']) {
            $this->deleteExistingFilesByPrefix($folder, $prefix, $disk);
        }

        return $this->fileUploadService->uploadFile(
            file: $file,
            folder: $folder,
            disk: $disk,
            customPrefix: $customCode ? Str::slug($customCode).'_'.$prefix : $prefix
        );
    }

    /**
     * حذف الملفات السابقة في مجلد معين وفق بادئة محددة.
     */
    protected function deleteExistingFilesByPrefix(string $folder, string $prefix, string $disk): void
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($folder)) {
            return;
        }

        $files = $storage->files($folder);
        foreach ($files as $file) {
            $baseName = basename($file);
            if (str_starts_with($baseName, $prefix)) {
                $storage->delete($file);
            }
        }
    }

    /**
     * حفظ محتوى خام كنص أو ثنائي (Binary) في مسار محدد في القرص المحمي.
     */
    public function putContent(string $path, string $content, ?string $disk = null): bool
    {
        $diskName = $disk ?? $this->protectedDisk;

        return Storage::disk($diskName)->put($path, $content);
    }

    /**
     * استرجاع المحتوى الخام لملف من القرص المحمي.
     */
    public function getContent(string $path, ?string $disk = null): ?string
    {
        $diskName = $disk ?? $this->protectedDisk;
        $storage = Storage::disk($diskName);

        if ($storage->exists($path)) {
            return $storage->get($path);
        }

        return null;
    }

    /**
     * حذف ملف بأمان من القرص المحمي عبر FileUploadService.
     */
    public function delete(string $path, ?string $disk = null): bool
    {
        $diskName = $disk ?? $this->protectedDisk;

        return $this->fileUploadService->deleteFile($path, $diskName);
    }

    /**
     * جلب رابط وصول للملف من خلال FileUploadService.
     */
    public function url(?string $path, ?string $disk = null): ?string
    {
        $diskName = $disk ?? $this->protectedDisk;

        return $this->fileUploadService->url($path, $diskName);
    }

    /**
     * توليد رابط مؤقت مشفر وموقع رقمياً (Temporary Signed URL) للملفات المحمية.
     */
    public function temporaryUrl(string $path, int $expirationMinutes = 30, ?string $disk = null): ?string
    {
        $diskName = $disk ?? $this->protectedDisk;
        $storage = Storage::disk($diskName);

        if (! $storage->exists($path)) {
            return null;
        }

        return $this->fileUploadService->temporarySignedUrl($path, $expirationMinutes);
    }

    /**
     * فحص وجود الملف في القرص المحمي.
     */
    public function exists(string $path, ?string $disk = null): bool
    {
        $diskName = $disk ?? $this->protectedDisk;

        return $this->fileUploadService->exists($path, $diskName);
    }
}
