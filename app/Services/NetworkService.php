<?php

namespace App\Services;

use App\Enums\NetworkStatus;
use App\Enums\NotificationType;
use App\Enums\SmsMessageType;
use App\Enums\ProviderSubscriptionStatus;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Http\Requests\API\V1\Network\StoreNetworkRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkRequest;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkSmsSettingRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Interfaces\ProviderSubscriptionRepositoryInterface;
use App\Interfaces\SubscriptionPlanRepositoryInterface;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\Network;
use App\Services\Support\FileUploadService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة مزودي الشبكات والاعتمادات (Enterprise Network Service).
 *
 * تتولى كافة العمليات المنطقية لمزودي الشبكات:
 * 1. استرجاع والبحث والترقيم لمزودي الشبكات عبر مستودع NetworkRepository.
 * 2. إنشاء وتحديث بيانات الشبكات ورفع وإدارة الشعارات الرسمية.
 * 3. الحذف المؤقت (Soft Delete) والاستعادة والحذف النهائي والعمليات المجمعة (Bulk Operations).
 * 4. إدارة معاملات الاعتماد والموافقة (Approve) والرفض (Reject) وتوزيع وظائف الإشعارات.
 * 5. التوثيق الأمني التلقائي لكافة الحركات عبر BaseRepository.
 */
class NetworkService
{
    public function __construct(
        protected NetworkRepositoryInterface $networkRepository,
        protected UserRepositoryInterface $userRepository,
        protected UserDeviceRepositoryInterface $userDeviceRepository,
        protected FileUploadService $fileUploadService,
        protected NotificationService $notificationService,
        protected NetworkWalletRepositoryInterface $networkWalletRepository,
        protected ProviderSubscriptionRepositoryInterface $providerSubscriptionRepository,
        protected SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        protected SystemSettingService $systemSettingService,
        protected NetworkSmsSettingRepositoryInterface $networkSmsSettingRepository,
    ) {}

    /**
     * جلب قائمة مزودي الشبكات مع تطبيق الفلاتر والبحث والترقيم.
     */
    public function getFiltered(array $filters = [], array $relations = ['governorate', 'directorate', 'wallet']): Collection|CursorPaginator
    {
        return $this->networkRepository->getFiltered($filters, $relations);
    }

    /**
     * تحديث رقم الهاتف لجميع الشبكات التي يمتلكها المستخدم (owner_id).
     */
    public function syncPhoneForUser(int $ownerId, string $phone): int
    {
        return $this->networkRepository->syncPhoneForUser($ownerId, $phone);
    }

    /**
     * البحث عن شبكة عبر المعرف.
     */
    public function findById(int $id, array $relations = ['governorate', 'directorate', 'wallet']): ?Network
    {
        try {
            $network = $this->networkRepository->findById($id);

            return ! empty($relations) ? $network->load($relations) : $network;
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * إنشاء شبكة جديدة إدارياً.
     */
    public function create(StoreNetworkRequest|array $request, ?int $ownerId = null, ?UploadedFile $logo = null): Network
    {
        if ($request instanceof StoreNetworkRequest) {
            $logo = $request->file('network_profile_logo') ?? $request->file('network_logo');
            $data = [
                'network_name' => (string) $request->network_name,
                'governorate_id' => (int) $request->governorate_id,
                'directorate_id' => (int) $request->directorate_id,
                'network_email' => $request->network_email,
                'network_address' => $request->network_address,
                'status' => $request->status ?? NetworkStatus::ACTIVE,
            ];
        } else {
            $data = $request;
        }

        $resolvedOwnerId = $ownerId ?? ($data['owner_id'] ?? (auth()->id() ? (int) auth()->id() : null));
        if (! $resolvedOwnerId) {
            throw ValidationException::withMessages([
                'owner_id' => ['تعذر تحديد مالك الشبكة من الجلسة أو الطلب.'],
            ]);
        }
        $owner = $this->userRepository->findById($resolvedOwnerId);
        if (! $owner) {
            throw ValidationException::withMessages([
                'owner_id' => ['مالك الشبكة المحدد غير موجود بالنظام.'],
            ]);
        }
        $owner->ensureActive();

        $data['owner_id'] = $owner->user_id;
        $data['network_phone_number'] = (string) $owner->user_phone_number;
        // unset($data['network_holder_name']);

        return DB::transaction(function () use ($data, $logo) {
            if ($logo) {
                $data['network_profile_logo'] = $this->fileUploadService->uploadFile($logo, 'networks/logos');
            }

            $network = $this->networkRepository->create($data);

            return $network->load(['governorate', 'directorate', 'owner']);
        });
    }

    /**
     * تحديث بيانات شبكة قائمة.
     */
    public function update(int $id, UpdateNetworkRequest|array $request, ?UploadedFile $logo = null): Network
    {
        try {
            $network = $this->networkRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'network_id' => ['الشبكة المحددة غير موجودة في النظام.'],
            ]);
        }

        if ($request instanceof UpdateNetworkRequest) {
            $logo = $request->file('network_profile_logo') ?? $request->file('network_logo');
            $data = array_filter([
                'network_name' => $request->network_name,
                'governorate_id' => $request->governorate_id !== null ? (int) $request->governorate_id : null,
                'directorate_id' => $request->directorate_id !== null ? (int) $request->directorate_id : null,
                'network_email' => $request->network_email,
                'network_address' => $request->network_address,
                'status' => $request->status,
            ], fn ($value) => $value !== null);
        } else {
            $data = $request;
        }

        return DB::transaction(function () use ($network, $data, $logo) {
            if ($logo) {
                $data['network_profile_logo'] = $this->fileUploadService->replaceFile(
                    $logo,
                    $network->network_profile_logo,
                    'networks/logos'
                );
            }

            $this->networkRepository->update($network->network_id, $data);
            $network->refresh();

            return $network->load(['governorate', 'directorate']);
        });
    }

    /**
     * الحذف المؤقت لمزود شبكة.
     */
    public function delete(int $id): bool
    {
        try {
            $network = $this->networkRepository->findById($id);
        } catch (ModelNotFoundException) {
            return false;
        }

        return DB::transaction(function () use ($network) {
            return $this->networkRepository->delete($network->network_id);
        });
    }

    /**
     * استرجاع الشبكات المحذوفة مؤقتاً.
     */
    public function getTrashed(int $perPage = 15): CursorPaginator
    {
        return $this->networkRepository->getTrashed($perPage);
    }

    /**
     * استعادة شبكة محذوفة مؤقتاً.
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->networkRepository->restore($id);
        });
    }

    /**
     * الحذف النهائي لشبكة من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->networkRepository->forceDelete($id);
        });
    }

    /**
     * حذف مجمع لعدة شبكات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->networkRepository->bulkDelete($ids);
        });
    }

    /**
     * استعادة مجمعة لعدة شبكات من سلة المحذوفات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkRestore(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->networkRepository->bulkRestore($ids);
        });
    }

    /**
     * حذف نهائي مجمع لعدة شبكات.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->networkRepository->bulkForceDelete($ids);
        });
    }

    /**
     * الموافقة على طلب مزود شبكة وتفعيل الحساب والأجهزة وإشعار المستخدم.
     */
    public function approve(int $id, int $adminUserId): Network
    {
        return DB::transaction(function () use ($id, $adminUserId) {
            try {
                $network = $this->networkRepository->findById($id);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'network_id' => ['الشبكة المحددة غير موجودة في النظام.'],
                ]);
            }

            $this->networkRepository->update($network->network_id, [
                'status' => NetworkStatus::ACTIVE,
                'approved_by' => $adminUserId,
                'approved_at' => now('Asia/Aden'),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $network->refresh();

            // 1. إنشاء محفظة مالية للشبكة برصيد 0.00 تلقائياً إذا لم تكن موجودة مسبقاً
            $wallet = $this->networkWalletRepository->findByNetworkId($network->network_id);
            if (! $wallet) {
                $this->networkWalletRepository->create([
                    'network_id' => $network->network_id,
                    'wallet_balance' => 0.00,
                    'status' => true,
                ]);
            }

            // 2. تفعيل اشتراك تجريبي مجاني للشبكة الجديدة وفق إعدادات النظام إذا لم يكن لديها اشتراك سارٍ
            $trialDays = $this->systemSettingService->getInt('trial_period_days', 14);
            $hasSub = $this->providerSubscriptionRepository->getActiveForNetwork($network->network_id);
            if ($trialDays > 0 && ! $hasSub) {
                $defaultPlan = $this->subscriptionPlanRepository->getActivePlans()->first();
                if ($defaultPlan) {
                    $this->providerSubscriptionRepository->create([
                        'network_id' => $network->network_id,
                        'plan_id' => $defaultPlan->plan_id,
                        'subscription_start_date' => now('Asia/Aden'),
                        'subscription_end_date' => now('Asia/Aden')->addDays($trialDays),
                        'status' => ProviderSubscriptionStatus::ACTIVE->value,
                        'created_by' => $adminUserId,
                    ]);
                }
            }

            // 3. إنشاء إعدادات الرسائل النصية للشبكة (تفعيل الإشعارات افتراضياً عبر بوابة النظام العامة)
            $this->networkSmsSettingRepository->getOrCreateForNetwork($network->network_id);

            // تفعيل المستخدم والجهاز المرتبط بمالك الشبكة
            $user = $network->owner;
            if ($user) {
                $this->userRepository->update($user->user_id, ['status' => UserStatus::ACTIVE]);
                $devices = $this->userDeviceRepository->getDevicesForUser($user->user_id);
                foreach ($devices as $device) {
                    $this->userDeviceRepository->setDeviceStatus($device->device_id, UserDeviceStatus::ACTIVE);
                }

                $this->notificationService->sendDirect(
                    userId: (int) $user->user_id,
                    type: NotificationType::NETWORK_APPROVED,
                    title: 'تمت الموافقة على طلب تسجيل الشبكة',
                    message: "تهانينا! تمت الموافقة على طلب تسجيل شبكة {$network->network_name} وتفعيل حسابكم بنجاح. يمكنك الآن تسجيل الدخول للنظام.",
                    inApp: true,
                    push: true,
                    sms: true,
                    email: true,
                    networkId: $network->network_id,
                    smsMessageType: SmsMessageType::ADMIN_BROADCAST,
                    emailSubject: 'الموافقة على طلب تسجيل الشبكة - NetSales'
                );
            }

            return $network->load(['governorate', 'directorate', 'owner']);
        });
    }

    /**
     * رفض طلب مزود شبكة وتوثيق السبب وإشعار المستخدم.
     */
    public function reject(int $id, int $adminUserId, string $reason): Network
    {
        return DB::transaction(function () use ($id, $adminUserId, $reason) {
            try {
                $network = $this->networkRepository->findById($id);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'network_id' => ['الشبكة المحددة غير موجودة في النظام.'],
                ]);
            }

            $this->networkRepository->update($network->network_id, [
                'status' => NetworkStatus::REJECTED,
                'rejected_by' => $adminUserId,
                'rejected_at' => now('Asia/Aden'),
                'rejection_reason' => $reason,
            ]);

            $network->refresh();

            $user = $network->owner;
            if ($user) {
                $this->notificationService->sendDirect(
                    userId: (int) $user->user_id,
                    type: NotificationType::NETWORK_REJECTED,
                    title: 'تم رفض طلب تسجيل شبكتكم',
                    message: "نأسف لإبلاغكم بأنه تم رفض طلب تسجيل شبكة {$network->network_name}. السبب: {$reason}",
                    inApp: true,
                    push: true,
                    sms: true,
                    email: false,
                    networkId: $network->network_id,
                    smsMessageType: SmsMessageType::ADMIN_BROADCAST
                );
            }

            return $network->load(['governorate', 'directorate']);
        });
    }
}
