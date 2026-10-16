<?php

namespace App\Services;

use App\Enums\ProviderSubscriptionStatus;
use App\Enums\PaymentType;
use App\Enums\RequestStatus;
use App\Http\Requests\API\V1\SubscriptionRequest\ApproveSubscriptionPlanRequestRequest;
use App\Http\Requests\API\V1\SubscriptionRequest\RejectSubscriptionPlanRequestRequest;
use App\Http\Requests\API\V1\SubscriptionRequest\StoreSubscriptionPlanRequestRequest;
use App\Interfaces\BankAccountRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\ProviderSubscriptionRepositoryInterface;
use App\Interfaces\SubscriptionPlanFeatureConsumptionRepositoryInterface;
use App\Interfaces\SubscriptionPlanRepositoryInterface;
use App\Interfaces\SubscriptionPlanRequestRepositoryInterface;
use App\Models\ProviderSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanRequest;
use App\Services\Support\FileUploadService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة دورة حياة طلبات اشتراك الباقات وتدقيقها واعتمادها (Subscription Request Service).
 */
class SubscriptionRequestService
{
    public function __construct(
        protected SubscriptionPlanRequestRepositoryInterface $requestRepository,
        protected SubscriptionPlanRepositoryInterface $planRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected BankAccountRepositoryInterface $bankAccountRepository,
        protected ProviderSubscriptionRepositoryInterface $providerSubscriptionRepository,
        protected SubscriptionPlanFeatureConsumptionRepositoryInterface $consumptionRepository,
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * جلب طلبات الاشتراك مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->requestRepository->getFiltered($filters);
    }

    /**
     * جلب طلبات الاشتراك المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->requestRepository->getTrashedFiltered($filters);
    }

    /**
     * جلب تفاصيل طلب اشتراك محدد عبر معرفه.
     */
    public function getById(int $id): SubscriptionPlanRequest
    {
        /** @var SubscriptionPlanRequest|null $request */
        $request = $this->requestRepository->findById($id);
        if (! $request) {
            throw ValidationException::withMessages([
                'request_id' => ["طلب الاشتراك برقم [{$id}] غير موجود."],
            ]);
        }

        $request->load(['network', 'plan.planFeatures.feature', 'requestedBy', 'reviewedBy', 'bankAccount']);

        return $request;
    }

    /**
     * جلب تفاصيل طلب اشتراك عبر المعرف الفريد للأعمال.
     */
    public function getByUuid(string $uuid): SubscriptionPlanRequest
    {
        $request = $this->requestRepository->findByUuid($uuid);
        if (! $request) {
            throw ValidationException::withMessages([
                'request_uuid' => ["طلب الاشتراك بالمعرف [{$uuid}] غير موجود."],
            ]);
        }

        return $request;
    }

    /**
     * إنشاء وتقديم طلب اشتراك جديد لباقة محددة من قبل مزود الشبكة.
     */
    public function create(StoreSubscriptionPlanRequestRequest $request, int $userId): SubscriptionPlanRequest
    {
        return DB::transaction(function () use ($request, $userId) {
            $networkId = $request->getNetworkId();
            $network = $this->networkRepository->findById($networkId);
            if (! $network) {
                throw ValidationException::withMessages([
                    'network_id' => ["شبكة المزود برقم [{$networkId}] غير موجودة."],
                ]);
            }

            $planId = $request->getPlanId();
            /** @var SubscriptionPlan|null $plan */
            $plan = $this->planRepository->findById($planId);
            if (! $plan || ! $plan->status) {
                throw ValidationException::withMessages([
                    'plan_id' => ['باقة الاشتراك المحددة غير متاحة حالياً.'],
                ]);
            }

            // التحقق من عدم وجود طلب معلق للشبكة
            if ($this->requestRepository->hasPendingRequest($networkId)) {
                throw ValidationException::withMessages([
                    'network_id' => ['توجد عملية اشتراك معلقة قيد المراجعة لهذه الشبكة حالياً.'],
                ]);
            }

            $paymentType = $request->getRequestPaymentType();
            $accountId = $request->getAccountId();

            if ($paymentType === PaymentType::BANK->value && empty($accountId)) {
                throw ValidationException::withMessages([
                    'account_id' => ['يجب اختيار الحساب البنكي المحول إليه عند اختيار السداد البنكي.'],
                ]);
            }

            if (! empty($accountId)) {
                $account = $this->bankAccountRepository->findById($accountId);
                if (! $account || ! $account->status) {
                    throw ValidationException::withMessages([
                        'account_id' => ['الحساب البنكي المحدد غير مفعل أو غير متاح.'],
                    ]);
                }
            }

            // رفع إيصال السداد أو الحوالة إن وجد
            $receiptPath = null;
            if ($request->hasFile('transaction_receipt')) {
                $receiptPath = $this->fileUploadService->uploadFile(
                    $request->file('transaction_receipt'),
                    'subscriptions/receipts'
                );
            }

            // احتساب المبلغ الصافي بعد خصم الباقة
            $totalAmount = $plan->finalPrice();

            $requestData = [
                'network_id' => $networkId,
                'plan_id' => $planId,
                'requested_by' => $userId,
                'request_total_amount' => $totalAmount,
                'request_payment_type' => $paymentType,
                'account_id' => $accountId,
                'transaction_reference' => $request->getTransactionReference(),
                'transaction_receipt' => $receiptPath,
                'status' => RequestStatus::PENDING->value,
                'request_note' => $request->getRequestNote(),
            ];

            /** @var SubscriptionPlanRequest $subRequest */
            $subRequest = $this->requestRepository->create($requestData);

            return $this->getById($subRequest->request_id);
        });
    }

    /**
     * اعتماد طلب الاشتراك وتوليد الاشتراك النشط للمزود وتهيئة رصيد استهلاك الميزات.
     */
    public function approve(int $id, ApproveSubscriptionPlanRequestRequest $request, int $reviewerId): array
    {
        return DB::transaction(function () use ($id, $request, $reviewerId) {
            $subRequest = $this->getById($id);

            if ($subRequest->status !== RequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => ["لا يمكن اعتماد هذا الطلب لأن حالته الحالية هي [{$subRequest->status->label()}]."],
                ]);
            }

            /** @var SubscriptionPlan $plan */
            $plan = $this->planRepository->findById($subRequest->plan_id);
            if (! $plan) {
                throw ValidationException::withMessages([
                    'plan_id' => ['باقة الاشتراك المرتبطة بالطلب غير موجودة.'],
                ]);
            }

            // 1. تحديث حالة الطلب إلى معتمد
            $updatedRequest = $this->requestRepository->updateStatus(
                $id,
                RequestStatus::APPROVED,
                $reviewerId,
                null,
                $request->getNote()
            );

            // 2. تحديث رصيد الحساب البنكي إذا كان السداد بنكياً
            if ($subRequest->account_id && (float) $subRequest->request_total_amount > 0) {
                $this->bankAccountRepository->updateBalance(
                    $subRequest->account_id,
                    (float) $subRequest->request_total_amount,
                    true
                );
            }

            // 3. التحقق مما إذا كان للشبكة اشتراك نشط حالياً لجدولة الجديد في طابور الانتظار (QUEUED) أو تفعيله فوراً
            $activeSub = $this->providerSubscriptionRepository->getActiveForNetwork($subRequest->network_id);

            if ($activeSub && $activeSub->isActive()) {
                $startDate = $activeSub->subscription_end_date ? Carbon::parse($activeSub->subscription_end_date) : now('Asia/Aden');
                $endDate = (clone $startDate)->addDays($plan->plan_validity);
                $status = ProviderSubscriptionStatus::QUEUED->value;
            } else {
                $startDate = now('Asia/Aden');
                $endDate = (clone $startDate)->addDays($plan->plan_validity);
                $status = ProviderSubscriptionStatus::ACTIVE->value;
            }

            /** @var ProviderSubscription $subscription */
            $subscription = $this->providerSubscriptionRepository->create([
                'network_id' => $subRequest->network_id,
                'plan_id' => $plan->plan_id,
                'subscription_start_date' => $startDate,
                'subscription_end_date' => $endDate,
                'status' => $status,
                'created_by' => $reviewerId,
            ]);

            // 4. تهيئة حصص الميزات بالصفر للاشتراك الجديد
            $planFeatures = $plan->planFeatures()->pluck('subscription_plan_feature_id')->toArray();
            if (! empty($planFeatures)) {
                $this->consumptionRepository->initializeForSubscription($subscription->subscription_id, $planFeatures);
            }

            $subscription->load(['plan.planFeatures.feature', 'consumptions.planFeature.feature', 'network']);

            return [
                'request' => $updatedRequest,
                'subscription' => $subscription,
            ];
        });
    }

    /**
     * رفض طلب الاشتراك مع توثيق السبب.
     */
    public function reject(int $id, RejectSubscriptionPlanRequestRequest $request, int $reviewerId): SubscriptionPlanRequest
    {
        return DB::transaction(function () use ($id, $request, $reviewerId) {
            $subRequest = $this->getById($id);

            if ($subRequest->status !== RequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => ["لا يمكن رفض هذا الطلب لأن حالته الحالية هي [{$subRequest->status->label()}]."],
                ]);
            }

            $updatedRequest = $this->requestRepository->updateStatus(
                $id,
                RequestStatus::REJECTED,
                $reviewerId,
                $request->getReason(),
                $request->getNote()
            );

            return $updatedRequest;
        });
    }

    /**
     * إلغاء طلب اشتراك معلق من قبل المزود.
     */
    public function cancel(int $id, int $userId): SubscriptionPlanRequest
    {
        return DB::transaction(function () use ($id, $userId) {
            $subRequest = $this->getById($id);

            if ($subRequest->status !== RequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن إلغاء الطلب إلا إذا كان في حالة انتظار المراجعة.'],
                ]);
            }

            $subRequest->status = RequestStatus::CANCELLED;
            $subRequest->reviewed_by = $userId;
            $subRequest->review_date = now('Asia/Aden');
            $subRequest->request_note = 'تم إلغاء الطلب من قبل المستخدم.';
            $subRequest->save();

            return $subRequest;
        });
    }

    /**
     * حذف طلب اشتراك ناعماً.
     */
    public function delete(int $id): bool
    {
        $this->getById($id);

        return (bool) $this->requestRepository->delete($id);
    }

    /**
     * استعادة طلب محذوف ناعماً.
     */
    public function restore(int $id): bool
    {
        $request = $this->requestRepository->findTrashedById($id);
        if (! $request) {
            throw ValidationException::withMessages([
                'request_id' => ["طلب الاشتراك المحذوف برقم [{$id}] غير موجود."],
            ]);
        }

        return (bool) $this->requestRepository->restore($id);
    }

    /**
     * حذف نهائي لطلب الاشتراك مع تنظيف إيصال السداد.
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $subRequest = $this->requestRepository->findTrashedById($id);
            if (! $subRequest) {
                throw ValidationException::withMessages([
                    'request_id' => ["طلب الاشتراك المحذوف برقم [{$id}] غير موجود."],
                ]);
            }

            if ($subRequest->transaction_receipt) {
                $this->fileUploadService->deleteFile($subRequest->transaction_receipt);
            }

            return (bool) $this->requestRepository->forceDelete($id);
        });
    }

    /**
     * حذف مجمع ناعم لطلبات الاشتراك.
     */
    public function batchDelete(array $ids): int
    {
        return $this->requestRepository->bulkDelete($ids);
    }

    /**
     * استعادة مجمعة لطلبات الاشتراك.
     */
    public function batchRestore(array $ids): int
    {
        return $this->requestRepository->bulkRestore($ids);
    }

    /**
     * حذف نهائي مجمع لطلبات الاشتراك مع تنظيف الإيصالات.
     */
    public function batchForceDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $subRequest = $this->requestRepository->findTrashedById((int) $id);
                if ($subRequest && $subRequest->transaction_receipt) {
                    $this->fileUploadService->deleteFile($subRequest->transaction_receipt);
                }
            }

            return $this->requestRepository->bulkForceDelete($ids);
        });
    }
}
