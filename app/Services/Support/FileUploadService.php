<?php

namespace App\Services\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * خدمة إدارة ورفع واستبدال الملفات (Enterprise File Upload Service).
 *
 * صُممت لتكون مستقلة تماماً وقابلة لإعادة الاستخدام في أي مشروع Laravel:
 * 1. دعم التخصيص الكامل للأقراص (Disks) والمجلدات (Folders).
 * 2. توليد أسماء عشوائية آمنة وفريدة تمنع تصادم الملفات وحقن المسارات.
 * 3. حماية الملفات والصور الافتراضية للنظام من الحذف العرضي.
 * 4. دعم التحقق من الامتدادات والأحجام المسموحة.
 * 5. دعم استخراج الروابط العامة والمعلومات الوصفية (Metadata).
 */
class FileUploadService
{
    /**
     * القرص الافتراضي للتخزين عند عدم التحديد (محمي آمن للمؤسسات).
     */
    protected string $defaultDisk = 'protected';

    /**
     * الكلمات المفتاحية للمسارات المحمية من الحذف (مثل الصور الافتراضية).
     *
     * @var array<string>
     */
    protected array $protectedPatterns = [
        'default',
        'placeholder',
        'no-avatar',
        'system/',
    ];

    public function __construct(?string $defaultDisk = null)
    {
        if ($defaultDisk) {
            $this->defaultDisk = $defaultDisk;
        }
    }

    /**
     * رفع وحفظ ملف جديد.
     *
     * @param  UploadedFile  $file  الملف المرفوع
     * @param  string  $folder  المجلد الفرعي داخل القرص
     * @param  string|null  $disk  اسم القرص (اختياري، الافتراضي public)
     * @param  string|null  $customPrefix  بادئة اختيارية لاسم الملف
     * @return string المسار النسبي للملف المخزن
     */
    public function uploadFile(
        UploadedFile $file,
        string $folder = 'uploads',
        ?string $disk = null,
        ?string $customPrefix = null
    ): string {
        $diskName = $disk ?? $this->defaultDisk;
        $folder = trim($folder, '/');

        $extension = strtolower($file->getClientOriginalExtension());
        $prefix = $customPrefix ? Str::slug($customPrefix).'_' : '';
        $filename = $prefix.Str::uuid()->toString().'_'.time().'.'.$extension;

        $path = $file->storeAs($folder, $filename, $diskName);

        if ($path === false) {
            throw new InvalidArgumentException("فشل حفظ الملف على القرص [{$diskName}].");
        }

        return $path;
    }

    /**
     * رفع صورة الملف الشخصي أو الشعار.
     */
    public function uploadProfilePicture(
        UploadedFile $file,
        string $folder = 'users/avatars',
        ?string $disk = null
    ): string {
        return $this->uploadFile($file, $folder, $disk, 'avatar');
    }

    /**
     * استبدال ملف قديم بملف جديد مع حذف القديم بأمان.
     *
     * @param  UploadedFile  $newFile  الملف الجديد
     * @param  string|null  $oldFilePath  مسار الملف القديم المراد حذفه
     * @param  string  $folder  مجلد التخزين
     * @param  string|null  $disk  اسم القرص
     * @param  string|null  $customPrefix  بادئة لاسم الملف الجديد
     * @return string المسار الجديد للملف
     */
    public function replaceFile(
        UploadedFile $newFile,
        ?string $oldFilePath,
        string $folder = 'uploads',
        ?string $disk = null,
        ?string $customPrefix = null
    ): string {
        $diskName = $disk ?? $this->defaultDisk;

        // حذف الملف القديم أولاً إذا كان موجوداً وغير محمي
        if (! empty($oldFilePath)) {
            $this->deleteFile($oldFilePath, $diskName);
        }

        return $this->uploadFile($newFile, $folder, $diskName, $customPrefix);
    }

    /**
     * حذف ملف بأمان مع تجاهل وحماية الملفات الافتراضية.
     *
     * @param  string|null  $filePath  مسار الملف النسبي
     * @param  string|null  $disk  اسم القرص
     * @return bool نجاح أو فشل الحذف
     */
    public function deleteFile(?string $filePath, ?string $disk = null): bool
    {
        if (empty($filePath)) {
            return false;
        }

        $diskName = $disk ?? $this->defaultDisk;

        // التحقق من أن المسار لا يمثل ملفاً افتراضياً أو قالباً محمياً
        foreach ($this->protectedPatterns as $pattern) {
            if (Str::contains($filePath, $pattern)) {
                return false;
            }
        }

        $storage = Storage::disk($diskName);

        if ($storage->exists($filePath)) {
            return $storage->delete($filePath);
        }

        return false;
    }

    /**
     * جلب الرابط الكامل (URL) للوصول إلى الملف.
     *
     * @param  string|null  $filePath  مسار الملف
     * @param  string|null  $disk  اسم القرص
     * @return string|null الرابط المباشر للملف
     */
    public function url(?string $filePath, ?string $disk = null): ?string
    {
        if (empty($filePath)) {
            return null;
        }

        // إذا كان الرابط يبدأ بـ http/https يُعاد مباشرة
        if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
            return $filePath;
        }

        $diskName = $disk ?? $this->defaultDisk;

        if ($diskName === 'protected') {
            return url('/api/v1/files/'.ltrim($filePath, '/'));
        }

        return Storage::disk($diskName)->url($filePath);
    }

    /**
     * توليد رابط مؤقت موقع رقمياً (Cryptographically Signed Ephemeral URL) للملفات الحساسة المحمية.
     *
     * يعتمد على خوارزمية HMAC-SHA256 عبر مفتاح النظام APP_KEY لمنع التلاعب وتحديد مدة صلاحية دقيقة.
     *
     * @param  string|null  $filePath  مسار الملف النسبي
     * @param  int  $expirationMinutes  مدة الصلاحية بالدقائق (الافتراضي 30 دقيقة)
     * @return string|null الرابط الموقع رقمياً
     */
    public function temporarySignedUrl(?string $filePath, ?int $expirationMinutes = null): ?string
    {
        if (empty($filePath)) {
            return null;
        }

        $cleanPath = ltrim($filePath, '/');
        $effectiveMinutes = $expirationMinutes ?? (int) systemSetting('signed_url_lifetime_minutes', 30);

        return URL::temporarySignedRoute(
            'api.v1.files.signed',
            now('Asia/Aden')->addMinutes($effectiveMinutes),
            ['path' => $cleanPath]
        );
    }

    /**
     * التحقق من وجود الملف في القرص.
     */
    public function exists(?string $filePath, ?string $disk = null): bool
    {
        if (empty($filePath)) {
            return false;
        }

        $diskName = $disk ?? $this->defaultDisk;

        return Storage::disk($diskName)->exists($filePath);
    }

    /**
     * جلب حجم الملف بالبايت.
     */
    public function size(string $filePath, ?string $disk = null): int
    {
        $diskName = $disk ?? $this->defaultDisk;

        return Storage::disk($diskName)->size($filePath);
    }

    /**
     * جلب نوع MIME للملف.
     */
    public function mimeType(string $filePath, ?string $disk = null): ?string
    {
        $diskName = $disk ?? $this->defaultDisk;

        return Storage::disk($diskName)->mimeType($filePath) ?: null;
    }

    /**
     * التحقق من صحة امتداد الملف وحجمه وفق قواعد مرنة وديناميكية.
     *
     * @param  UploadedFile  $file  الملف
     * @param  array<string>  $allowedExtensions  الامتدادات المسموحة (مثل: ['jpg', 'png', 'pdf'])
     * @param  int|null  $maxKilobytes  أقصى حجم بالكيلوبايت (اختياري، يقرأ من الإعدادات ديناميكياً)
     */
    public function validateFile(UploadedFile $file, array $allowedExtensions = [], ?int $maxKilobytes = null): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! empty($allowedExtensions) && ! in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        $effectiveMaxKb = $maxKilobytes ?? ((int) systemSetting('max_file_size_mb', 5) * 1024);

        if (($file->getSize() / 1024) > $effectiveMaxKb) {
            return false;
        }

        return true;
    }
}
