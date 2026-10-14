<?php

namespace App\Services;

use App\Models\SystemLog;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * خدمة سجل النظام المركزية (Centralized System Logger Service).
 *
 * صُممت وفق أفضل المعايير المعمارية للمهندسين المحترفين:
 * 1. تسجيل شامل ودقيق لأي عملية تحدث في النظام.
 * 2. التزام صارم بجدول system_logs في قاعدة البيانات.
 * 3. حماية أمنية تلقائية للمعلومات الحساسة (Data Sanitization).
 * 4. أمان ضد التوقف (Fail-Safe Resilience): عدم مقاطعة العمليات الرئيسية في حال تعثر السجل.
 * 5. تدعم الاستخدام عبر الحقن (DI) أو الاستدعاء السريع (Static Facade).
 */
class SystemLoggerService
{
    /**
     * الحقول الحساسة المحظور تخزين قيمتها الحقيقية في سجلات التدقيق.
     */
    protected static array $sensitiveKeys = [
        'password',
        'user_password',
        'password_confirmation',
        'token',
        'token_hash',
        'remember_token',
        'code_hash',
        'otp',
        'secret',
        'api_key',
        'credit_card',
        'cvv',
    ];

    /**
     * تسجيل عملية في سجل النظام.
     *
     * @param  string  $module  اسم الوحدة (مثل: auth, academic, centers, grading, users)
     * @param  string  $action  الإجراء (مثل: login, create, update, delete, restore, force_delete)
     * @param  string|null  $entityType  نوع الكيان (مثل: User, Center, AcademicClass)
     * @param  int|null  $entityId  معرف الكيان
     * @param  array|null  $oldData  البيانات السابقة قبل التعديل (إن وجدت)
     * @param  array|null  $newData  البيانات الجديدة بعد العملية (إن وجدت)
     * @param  int|null  $userId  معرف المستخدم المنفذ (اختياري، يكتشف تلقائياً من الجلسة)
     * @param  string|null  $ipAddress  عنوان IP (اختياري، يكتشف تلقائياً)
     * @param  string|null  $userAgent  معلومات المتصفح/الجهاز (اختياري، يكتشف تلقائياً)
     */
    public static function log(
        string $module,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ?SystemLog {
        try {
            // 1. استنتاج السياق تلقائياً إن لم يمرر صراحة
            $resolvedUserId = $userId ?? Auth::id();

            $rawIp = $ipAddress ?? request()?->ip() ?? '127.0.0.1';
            // عمود ip_address في قاعدة البيانات VARCHAR(20)
            $resolvedIp = substr($rawIp, 0, 20);

            $resolvedUserAgent = $userAgent ?? request()?->userAgent();

            // 2. تنقية البيانات وتجريدها من الأسرار وكلمات المرور
            $sanitizedOld = $oldData !== null ? static::sanitizeData($oldData) : null;
            $sanitizedNew = $newData !== null ? static::sanitizeData($newData) : null;

            // 3. كتابة السجل في جدول system_logs
            return SystemLog::create([
                'user_id' => $resolvedUserId,
                'module' => substr($module, 0, 50),
                'action' => substr($action, 0, 50),
                'entity_type' => $entityType ? substr($entityType, 0, 50) : null,
                'entity_id' => $entityId,
                'old_values' => $sanitizedOld,
                'new_values' => $sanitizedNew,
                'ip_address' => $resolvedIp,
                'user_agent' => $resolvedUserAgent,
                'log_date' => now('Asia/Aden'),
            ]);
        } catch (Exception $e) {
            // ضمان عدم توقف النظام الأساسي في حال تعثر السجل، مع توثيق الخطأ في Laravel Log
            Log::error('SystemLoggerService Error: '.$e->getMessage(), [
                'module' => $module,
                'action' => $action,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * تسجيل تغييرات كائن Model تلقائياً (Create / Update / Delete / Restore).
     */
    public static function logModel(
        Model $model,
        string $action,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $module = null
    ): ?SystemLog {
        $entityType = class_basename($model);
        $entityId = (int) $model->getKey();
        $resolvedModule = $module ?? strtolower($entityType);

        return static::log(
            module: $resolvedModule,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            oldData: $oldData,
            newData: $newData
        );
    }

    /**
     * تسجيل أحداث المصادقة والأمان (Login, Logout, Failed Login, 2FA, Ban, etc.).
     */
    public static function logAuth(string $action, ?int $userId = null, ?array $details = null): ?SystemLog
    {
        return static::log(
            module: 'auth',
            action: $action,
            entityType: 'User',
            entityId: $userId,
            newData: $details
        );
    }

    /**
     * تسجيل معلومة عامة (Info Level Activity).
     */
    public static function info(string $action, array $context = [], ?int $userId = null, string $module = 'system'): ?SystemLog
    {
        return static::log(
            module: $module,
            action: $action,
            newData: $context,
            userId: $userId
        );
    }

    /**
     * تسجيل تنبيه أو محاولة غير مصرح بها (Security Warning).
     */
    public static function warning(string $action, array $context = [], ?int $userId = null, string $module = 'security'): ?SystemLog
    {
        return static::log(
            module: $module,
            action: $action,
            newData: $context,
            userId: $userId
        );
    }

    /**
     * تسجيل استثناء أو خطأ عملياتي (Operational Error).
     */
    public static function error(string $action, array $context = [], ?int $userId = null, string $module = 'system'): ?SystemLog
    {
        return static::log(
            module: $module,
            action: $action,
            newData: $context,
            userId: $userId
        );
    }

    /**
     * تسجيل عملية إضافة سجل (Bridge helper).
     */
    public static function logAddition(Model|array $record, ?string $module = null): ?SystemLog
    {
        if ($record instanceof Model) {
            return static::logModel($record, 'create', null, $record->toArray(), $module);
        }

        return static::log(
            module: $module ?? 'system',
            action: 'create',
            newData: $record
        );
    }

    /**
     * تسجيل عملية تعديل سجل (Bridge helper).
     */
    public static function logModification(array|Model $oldOrModel, array $newData = [], ?string $module = null): ?SystemLog
    {
        if ($oldOrModel instanceof Model) {
            return static::logModel($oldOrModel, 'update', null, $newData, $module);
        }

        return static::log(
            module: $module ?? 'system',
            action: 'update',
            oldData: $oldOrModel,
            newData: $newData
        );
    }

    /**
     * تسجيل عملية حذف سجل (Bridge helper).
     */
    public static function logDeletion(Model|array $record, ?string $module = null): ?SystemLog
    {
        if ($record instanceof Model) {
            return static::logModel($record, 'delete', $record->toArray(), null, $module);
        }

        return static::log(
            module: $module ?? 'system',
            action: 'delete',
            oldData: $record
        );
    }

    /**
     * تنقية المصفوفات واستبدال الحقول الحساسة برمز الحجب.
     */
    public static function sanitizeData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, static::$sensitiveKeys, true)) {
                $sanitized[$key] = '********';
            } elseif (is_array($value)) {
                $sanitized[$key] = static::sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
