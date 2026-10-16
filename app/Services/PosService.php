<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\PosStatus;
use App\Enums\SmsMessageType;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Http\Requests\API\V1\Pos\StorePosRequest;
use App\Http\Requests\API\V1\Pos\UpdatePosRequest;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\PosWalletRepositoryInterface;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\Pos;
use App\Services\Support\FileUploadService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة نقاط البيع والوكلاء والاعتمادات (Enterprise POS Service).
 *
 * تتولى كافة العمليات المنطقية لنقاط البيع والوكلاء:
 * 1. استرجاع والبحث والترقيم لنقاط البيع عبر مستودع PosRepository.
 * 2. إنشاء وتحديث بيانات نقاط البيع ورفع وإدارة الشعارات والصور.
 * 3. الحذف المؤقت (Soft Delete) والاستعادة والحذف النهائي والعمليات المجمعة (Bulk Operations).
 * 4. إدارة معاملات الاعتماد والموافقة (Approve) والرفض (Reject) وتفعيل الحسابات وتوزيع وظائف الإشعارات.
 * 5. التوثيق الأمني التلقائي لكافة الحركات عبر BaseRepository.
 */
class PosService
{
    public function __construct(
        protected PosRepositoryInterface $posRepository,
        protected UserRepositoryInterface $userRepository,
        protected UserDeviceRepositoryInterface $userDeviceRepository,
        protected FileUploadService $fileUploadService,
        protected NotificationService $notificationService,
        protected NetworkPosRepositoryInterface $networkPosRepository,
        protected PosWalletRepositoryInterface $posWalletRepository,
    ) {}

    /**
     * جلب قائمة نقاط البيع مع تطبيق الفلاتر والبحث والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = ['governorate', 'directorate', 'wallets']): Collection|CursorPaginator
    {
        return $this->posRepository->getFiltered($filters, $relations);
    }

    /**
     * البحث عن نقطة بيع عبر المعرف مع تحميل العلاقات المطلوبة.
     */
    public function findById(int $id, array $relations = ['governorate', 'directorate', 'wallets']): ?Pos
    {
        try {
            $pos = $this->posRepository->findById($id);

            return ! empty($relations) ? $pos->load($relations) : $pos;
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * إنشاء نقطة بيع جديدة إدارياً.
     */
    public function create(StorePosRequest|array $request, ?UploadedFile $logo = null): Pos
    {
        if ($request instanceof StorePosRequest) {
            $logo = $request->file('pos_profile_logo') ?? $request->file('logo');
            $data = [
                'pos_name' => (string) $request->pos_name,
                'pos_holder_name' => (string) ($request->pos_holder_name ?? $request->pos_name),
                'governorate_id' => (int) $request->governorate_id,
                'directorate_id' => (int) $request->directorate_id,
                'pos_phone_number' => (string) ($request->pos_phone_number ?? $request->pos_phone),
                'pos_email' => $request->pos_email,
                'pos_address' => $request->pos_address,
                'status' => $request->status ?? PosStatus::ACTIVE,
            ];
        } else {
            $data = $request;
        }

        return DB::transaction(function () use ($data, $logo) {
            if ($logo) {
                $data['pos_profile_logo'] = $this->fileUploadService->uploadFile($logo, 'pos/logos');
            }

            $pos = $this->posRepository->create($data);

            return $pos->load(['governorate', 'directorate']);
        });
    }

    /**
     * تحديث بيانات نقطة بيع قائمة.
     */
    public function update(int $id, UpdatePosRequest|array $request, ?UploadedFile $logo = null): Pos
    {
        try {
            $pos = $this->posRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'pos_id' => ['نقطة البيع المحددة غير موجودة في النظام.'],
            ]);
        }

        if ($request instanceof UpdatePosRequest) {
            $logo = $request->file('pos_profile_logo') ?? $request->file('logo');
            $data = array_filter([
                'pos_name' => $request->pos_name,
                'pos_holder_name' => $request->pos_holder_name,
                'governorate_id' => $request->governorate_id !== null ? (int) $request->governorate_id : null,
                'directorate_id' => $request->directorate_id !== null ? (int) $request->directorate_id : null,
                'pos_phone_number' => $request->pos_phone_number ?? $request->pos_phone,
                'pos_email' => $request->pos_email,
                'pos_address' => $request->pos_address,
                'status' => $request->status,
            ], fn ($value) => $value !== null);
        } else {
            $data = $request;
        }

        return DB::transaction(function () use ($pos, $data, $logo) {
            if ($logo) {
                $data['pos_profile_logo'] = $this->fileUploadService->replaceFile(
                    $logo,
                    $pos->pos_profile_logo,
                    'pos/logos'
                );
            }

            $this->posRepository->update($pos->pos_id, $data);
            $pos->refresh();

            return $pos->load(['governorate', 'directorate']);
        });
    }

    /**
     * الحذف المؤقت لنقطة بيع.
     */
    public function delete(int $id): bool
    {
        try {
            $pos = $this->posRepository->findById($id);
        } catch (ModelNotFoundException) {
            return false;
        }

        return DB::transaction(function () use ($pos) {
            return $this->posRepository->delete($pos->pos_id);
        });
    }

    /**
     * استرجاع نقاط البيع المحذوفة مؤقتاً.
     */
    public function getTrashed(int $perPage = 15): CursorPaginator
    {
        return $this->posRepository->getTrashed($perPage);
    }

    /**
     * استعادة نقطة بيع محذوفة مؤقتاً.
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->posRepository->restore($id);
        });
    }

    /**
     * الحذف النهائي لنقطة بيع من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->posRepository->forceDelete($id);
        });
    }

    /**
     * حذف مجمع لعدة نقاط بيع.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->posRepository->bulkDelete($ids);
        });
    }

    /**
     * استعادة مجمعة لعدة نقاط بيع من سلة المحذوفات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->posRepository->bulkRestore($ids);
        });
    }

    /**
     * حذف نهائي مجمع لعدة نقاط بيع.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->posRepository->bulkForceDelete($ids);
        });
    }

    /**
     * الموافقة على طلب نقطة بيع وتفعيل الحساب والأجهزة والروابط وإشعار الوكيل.
     */
    public function approve(int $id, int $adminUserId): Pos
    {
        return DB::transaction(function () use ($id, $adminUserId) {
            try {
                $pos = $this->posRepository->findById($id);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير موجودة في النظام.'],
                ]);
            }

            $this->posRepository->update($pos->pos_id, [
                'status' => PosStatus::ACTIVE,
                'approved_by' => $adminUserId,
                'approved_at' => now('Asia/Aden'),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $pos->refresh();

            // تفعيل ربط الشبكات وإنشاء المحافظ المالية المشتركة إن وُجدت
            $links = $this->networkPosRepository->getNetworksForPos($pos->pos_id, ['all' => true]);
            $linkIdsToActivate = [];
            foreach ($links as $link) {
                if (! $link->status) {
                    $linkIdsToActivate[] = (int) $link->network_pos_id;
                }

                $existingWallet = $this->posWalletRepository->findByNetworkAndPos((int) $link->network_id, (int) $pos->pos_id);
                if (! $existingWallet) {
                    $this->posWalletRepository->create([
                        'network_id' => (int) $link->network_id,
                        'pos_id' => (int) $pos->pos_id,
                        'wallet_balance' => 0.00,
                        'status' => true,
                    ]);
                } else {
                    $this->posWalletRepository->updateStatus((int) $existingWallet->wallet_id, true);
                }
            }

            if (! empty($linkIdsToActivate)) {
                $this->networkPosRepository->bulkToggleStatus($linkIdsToActivate, true);
            }

            // تفعيل المستخدم والجهاز المرتبط برقم هاتف نقطة البيع
            $user = $this->userRepository->findByPhone($pos->pos_phone_number);
            if ($user) {
                $this->userRepository->update($user->user_id, ['status' => UserStatus::ACTIVE]);
                $devices = $this->userDeviceRepository->getDevicesForUser($user->user_id);
                foreach ($devices as $device) {
                    $this->userDeviceRepository->setDeviceStatus($device->device_id, UserDeviceStatus::ACTIVE);
                }

                $this->notificationService->sendDirect(
                    userId: (int) $user->user_id,
                    type: NotificationType::ACCOUNT_APPROVAL,
                    title: 'تمت الموافقة على طلب تسجيل نقطة البيع',
                    message: "تهانينا! تمت الموافقة على طلب تسجيل نقطة البيع {$pos->pos_name} وتفعيل حسابكم بنجاح. يمكنك الآن تسجيل الدخول للنظام.",
                    inApp: true,
                    push: true,
                    sms: true,
                    email: true,
                    networkId: null,
                    smsMessageType: SmsMessageType::ADMIN_BROADCAST,
                    emailSubject: 'الموافقة على طلب نقطة البيع - NetSales'
                );
            }

            return $pos->load(['governorate', 'directorate']);
        });
    }

    /**
     * رفض طلب نقطة بيع وتوثيق السبب وإشعار الوكيل.
     */
    public function reject(int $id, int $adminUserId, string $reason): Pos
    {
        return DB::transaction(function () use ($id, $adminUserId, $reason) {
            try {
                $pos = $this->posRepository->findById($id);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'pos_id' => ['نقطة البيع المحددة غير موجودة في النظام.'],
                ]);
            }

            $this->posRepository->update($pos->pos_id, [
                'status' => PosStatus::REJECTED,
                'rejected_by' => $adminUserId,
                'rejected_at' => now('Asia/Aden'),
                'rejection_reason' => $reason,
            ]);

            $pos->refresh();

            $user = $this->userRepository->findByPhone($pos->pos_phone_number);
            if ($user) {
                $this->notificationService->sendDirect(
                    userId: (int) $user->user_id,
                    type: NotificationType::ACCOUNT_REJECTION,
                    title: 'تم رفض طلب تسجيل نقطة البيع',
                    message: "نأسف لإبلاغكم بأنه تم رفض طلب تسجيل نقطة البيع {$pos->pos_name}. السبب: {$reason}",
                    inApp: true,
                    push: true,
                    sms: true,
                    email: false,
                    networkId: null,
                    smsMessageType: SmsMessageType::ADMIN_BROADCAST
                );
            }

            return $pos->load(['governorate', 'directorate']);
        });
    }
}
