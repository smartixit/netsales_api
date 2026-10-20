<?php

namespace App\Interfaces;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إدارة المستخدمين (User Repository Interface).
 *
 * تعرف كافة العمليات القياسية للبحث، الفلترة، الحركات الأمنية،
 * وإدارة الجلسات والأجهزة والصلاحيات المباشرة للمستخدمين.
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب المستخدمين مع الفلترة والترقيم عبر المؤشر (Cursor Pagination).
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, User>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * البحث عن مستخدم بواسطة رقم الهاتف مع إمكانية القفل المتشائم (Pessimistic Lock).
     */
    public function findByPhone(string $phone, bool $lock = false): ?User;

    /**
     * البحث عن مستخدم بواسطة البريد الإلكتروني.
     */
    public function findByEmail(string $email): ?User;

    /**
     * زيادة عداد محاولات تسجيل الدخول الفاشلة.
     */
    public function incrementLoginAttempts(int $userId): void;

    /**
     * تصفير عداد محاولات تسجيل الدخول بعد النجاح.
     */
    public function resetLoginAttempts(int $userId): void;

    /**
     * تحديث تاريخ آخر تسجيل دخول بنجاح.
     */
    public function updateLastLogin(int $userId): void;

    /**
     * تحديث كلمة مرور المستخدم المشفرة.
     */
    public function updatePassword(int $userId, string $hashedPassword): bool;

    /**
     * تفعيل أو تعطيل المصادقة الثنائية (2FA).
     */
    public function setTwoFactor(int $userId, bool $enabled): bool;

    /**
     * تحديث حالة الحساب (ACTIVE, INACTIVE, PENDING, BANNED).
     */
    public function setStatus(int $userId, UserStatus|string $status): bool;

    /**
     * مزامنة الصلاحيات المباشرة للمستخدم.
     *
     * @param  array<int, int>  $permissionIds
     */
    public function syncDirectPermissions(User $user, array $permissionIds): void;

    /**
     * جلب الجلسات النشطة للمستخدم.
     *
     * @return Collection<int, UserSession>
     */
    public function getActiveSessions(int $userId): Collection;

    /**
     * جلب أجهزة المستخدم المسجلة.
     *
     * @return Collection<int, UserDevice>
     */
    public function getDevices(int $userId): Collection;

    /**
     * جلب المستخدمين النشطين حسب الدور أو المعرفات.
     *
     * @param  array<int, int>|null  $userIds
     * @return Collection<int, User>
     */
    public function getActiveUsers(?int $roleId = null, ?array $userIds = null): Collection;
}
