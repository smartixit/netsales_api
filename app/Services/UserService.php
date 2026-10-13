<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Http\Requests\API\V1\User\StoreUserRequest;
use App\Http\Requests\API\V1\User\UpdateUserRequest;
use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Services\Support\FileUploadService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة المستخدمين والعمليات الإدارية (Enterprise User Service).
 *
 * تتولى كافة العمليات الإدارية للمستخدمين:
 * 1. إنشاء الحسابات وتشفير كلمات المرور والتحقق من الصلاحيات.
 * 2. إدارة وتحديث الملفات الشخصية والصور الرمزية (Avatars).
 * 3. الحذف المؤقت (Soft Delete) والاستعادة والحذف النهائي والعمليات المجمعة (Bulk Operations).
 * 4. مزامنة وتخصيص الصلاحيات المباشرة للمستخدمين.
 * 5. التوثيق الأمني التلقائي لكافة الحركات عبر BaseRepository.
 */
class UserService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected FileUploadService $fileUploadService,
        protected SessionValidatorService $sessionValidatorService,
    ) {}

    /**
     * جلب قائمة المستخدمين مع تطبيق الفلاتر والبحث والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = ['role', 'permissions']): Collection|CursorPaginator
    {
        return $this->userRepository->getFiltered($filters, $relations);
    }

    /**
     * البحث عن مستخدم عبر معرفه مع تحميل العلاقات المطلوبة.
     */
    public function findById(int $id, array $relations = ['role', 'devices', 'permissions']): ?User
    {
        try {
            $user = $this->userRepository->findById($id);

            return ! empty($relations) ? $user->load($relations) : $user;
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * إنشاء مستخدم جديد وتشفير كلمة المرور ورفع الصورة الرمزية إن وجدت.
     */
    public function create(StoreUserRequest|array $request, ?UploadedFile $avatar = null): User
    {
        if ($request instanceof StoreUserRequest) {
            $avatar = $request->file('user_profile_logo') ?? $request->file('profile_picture');
            $data = [
                'role_id' => (int) $request->role_id,
                'user_full_name' => (string) $request->user_full_name,
                'user_phone_number' => (string) $request->user_phone_number,
                'user_email' => $request->user_email,
                'user_password' => (string) $request->user_password,
                'user_2fa_enabled' => (bool) $request->user_2fa_enabled,
                'status' => $request->status ?? UserStatus::ACTIVE,
            ];
        } else {
            $data = $request;
        }

        return DB::transaction(function () use ($data, $avatar) {
            if ($avatar) {
                $data['user_profile_logo'] = $this->fileUploadService->uploadProfilePicture($avatar, 'users/avatars');
            }

            if (! empty($data['user_password'])) {
                $data['user_password'] = Hash::make($data['user_password']);
            }

            $user = $this->userRepository->create($data);

            return $user->load(['role', 'permissions']);
        });
    }

    /**
     * تحديث بيانات المستخدم القائم ورفع الصورة واستبدالها إن وجدت.
     */
    public function update(int $id, UpdateUserRequest|array $request, ?UploadedFile $avatar = null): User
    {
        try {
            $user = $this->userRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'user_id' => ['المستخدم المحدد غير موجود في النظام.'],
            ]);
        }

        if ($request instanceof UpdateUserRequest) {
            $avatar = $request->file('user_profile_logo') ?? $request->file('profile_picture');
            $data = array_filter([
                'role_id' => $request->role_id !== null ? (int) $request->role_id : null,
                'user_full_name' => $request->user_full_name,
                'user_phone_number' => $request->user_phone_number,
                'user_email' => $request->user_email,
                'user_password' => $request->user_password,
                'user_2fa_enabled' => $request->user_2fa_enabled !== null ? (bool) $request->user_2fa_enabled : null,
                'status' => $request->status,
            ], fn ($value) => $value !== null);
        } else {
            $data = $request;
        }

        return DB::transaction(function () use ($user, $data, $avatar) {
            if ($avatar) {
                $data['user_profile_logo'] = $this->fileUploadService->replaceFile(
                    $avatar,
                    $user->user_profile_logo,
                    'users/avatars',
                    null,
                    'avatar'
                );
            }

            if (! empty($data['user_password'])) {
                $data['user_password'] = Hash::make($data['user_password']);
            } else {
                unset($data['user_password']);
            }

            $this->userRepository->update($user->user_id, $data);
            $user->refresh();

            return $user->load(['role', 'permissions']);
        });
    }

    /**
     * الحذف المؤقت لمستخدم مع إبطال كافة جلساته المفتوحة.
     */
    public function delete(int $id): bool
    {
        try {
            $user = $this->userRepository->findById($id);
        } catch (ModelNotFoundException) {
            return false;
        }

        return DB::transaction(function () use ($id) {
            $this->sessionValidatorService->invalidateAllUserSessions($id);

            return $this->userRepository->delete($id);
        });
    }

    /**
     * استعادة مستخدم محذوف مؤقتاً.
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->userRepository->restore($id);
        });
    }

    /**
     * الحذف النهائي للمستخدم من قاعدة البيانات مع حذف صورته.
     */
    public function forceDelete(int $id): bool
    {
        $user = User::withTrashed()->find($id);
        if (! $user) {
            return false;
        }

        return DB::transaction(function () use ($user, $id) {
            $this->sessionValidatorService->invalidateAllUserSessions($id);

            if ($user->user_profile_logo) {
                $this->fileUploadService->deleteFile($user->user_profile_logo);
            }

            return $this->userRepository->forceDelete($id);
        });
    }

    /**
     * الحذف المؤقت لمجموعة مستخدمين (Bulk Delete).
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->userRepository->bulkDelete($ids);
        });
    }

    /**
     * استعادة مجموعة مستخدمين محذوفين مؤقتاً (Bulk Restore).
     *
     * @param  array<int, int>  $ids
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->userRepository->bulkRestore($ids);
        });
    }

    /**
     * الحذف النهائي لمجموعة مستخدمين (Bulk Force Delete).
     *
     * @param  array<int, int>  $ids
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->userRepository->bulkForceDelete($ids);
        });
    }

    /**
     * جلب المستخدمين المحذوفين مؤقتاً.
     */
    public function getTrashed(int $perPage = 15): CursorPaginator
    {
        return $this->userRepository->getTrashed($perPage);
    }

    /**
     * مزامنة الصلاحيات المباشرة للمستخدم.
     *
     * @param  array<int, int>  $permissionIds
     */
    public function syncPermissions(int $userId, array $permissionIds): User
    {
        try {
            $user = $this->userRepository->findById($userId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'user_id' => ['المستخدم غير موجود في النظام.'],
            ]);
        }

        DB::transaction(function () use ($user, $permissionIds) {
            $this->userRepository->syncDirectPermissions($user, $permissionIds);
        });

        return $user->load(['role', 'permissions']);
    }

    /**
     * تحديث الصورة الشخصية للمستخدم فقط.
     */
    public function updateProfilePicture(int $userId, UploadedFile $file): string
    {
        try {
            $user = $this->userRepository->findById($userId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'user_id' => ['المستخدم غير موجود في النظام.'],
            ]);
        }

        return DB::transaction(function () use ($user, $file) {
            $newPath = $this->fileUploadService->replaceFile(
                $file,
                $user->user_profile_logo,
                'users/avatars',
                null,
                'avatar'
            );

            $this->userRepository->update($user->user_id, [
                'user_profile_logo' => $newPath,
            ]);

            return $newPath;
        });
    }
}
