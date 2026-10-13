<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة بيانات المستخدمين وحساباتهم (Enterprise User Repository).
 *
 * صُمم وفق أفضل الممارسات المعمارية لمشروع NetSales ويدعم:
 * 1. الفلترة المتقدمة والبحث النصي والترقيم بالمؤشر (Cursor Pagination).
 * 2. عمليات القفل المتشائم (Pessimistic Lock) لمنع التضارب وحالات السباق أثناء الدخول.
 * 3. المزامنة والربط المباشر مع الصلاحيات والجلسات والأجهزة.
 * 4. إدارة عداد الأمان وتحديثات الحالة.
 *
 * @property User $model
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة المستخدمين مع تطبيق الفلاتر والبحث والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $defaultRelations = ['role'];
        $withRelations = ! empty($relations) ? $relations : $defaultRelations;
        $query = $this->model->newQuery()->with($withRelations);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['role_id'])) {
            $query->where('role_id', (int) $filters['role_id']);
        }

        if (isset($filters['status'])) {
            $query->status($filters['status']);
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('user_id', 'desc')->get();
        }

        return $query->orderBy('user_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن مستخدم برقم الهاتف مع إمكانية القفل المتشائم.
     */
    public function findByPhone(string $phone, bool $lock = false): ?User
    {
        $query = $this->model->newQuery()->with(['role', 'permissions'])->phone($phone);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * البحث عن مستخدم بالبريد الإلكتروني.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->newQuery()->with(['role', 'permissions'])->email($email)->first();
    }

    /**
     * زيادة عداد محاولات تسجيل الدخول الفاشلة.
     */
    public function incrementLoginAttempts(int $userId): void
    {
        $this->model->newQuery()
            ->where('user_id', $userId)
            ->increment('user_login_attempts');
    }

    /**
     * تصفير عداد محاولات تسجيل الدخول.
     */
    public function resetLoginAttempts(int $userId): void
    {
        $this->model->newQuery()
            ->where('user_id', $userId)
            ->update(['user_login_attempts' => 0]);
    }

    /**
     * تحديث تاريخ آخر تسجيل دخول بنجاح.
     */
    public function updateLastLogin(int $userId): void
    {
        $this->model->newQuery()
            ->where('user_id', $userId)
            ->update([
                'last_login_at' => Carbon::now('Asia/Aden'),
                'user_login_attempts' => 0,
            ]);
    }

    /**
     * تحديث كلمة المرور المشفرة للمستخدم.
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        return (bool) $this->model->newQuery()
            ->where('user_id', $userId)
            ->update(['user_password' => $hashedPassword]);
    }

    /**
     * تفعيل أو تعطيل المصادقة الثنائية (2FA).
     */
    public function setTwoFactor(int $userId, bool $enabled): bool
    {
        return (bool) $this->model->newQuery()
            ->where('user_id', $userId)
            ->update(['user_2fa_enabled' => $enabled]);
    }

    /**
     * تحديث حالة الحساب.
     */
    public function setStatus(int $userId, UserStatus|string $status): bool
    {
        $statusValue = $status instanceof UserStatus ? $status->value : $status;

        return (bool) $this->model->newQuery()
            ->where('user_id', $userId)
            ->update(['status' => $statusValue]);
    }

    /**
     * مزامنة الصلاحيات المباشرة للمستخدم في جدول user_permissions.
     */
    public function syncDirectPermissions(User $user, array $permissionIds): void
    {
        $user->permissions()->sync($permissionIds);
    }

    /**
     * جلب الجلسات النشطة للمستخدم.
     */
    public function getActiveSessions(int $userId): Collection
    {
        /** @var User|null $user */
        $user = $this->model->find($userId);
        if (! $user) {
            return new Collection;
        }

        return $user->sessions()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now('Asia/Aden'))
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * جلب الأجهزة المسجلة لحساب المستخدم.
     */
    public function getDevices(int $userId): Collection
    {
        /** @var User|null $user */
        $user = $this->model->find($userId);
        if (! $user) {
            return new Collection;
        }

        return $user->devices()
            ->orderByDesc('last_used_at')
            ->get();
    }
}
