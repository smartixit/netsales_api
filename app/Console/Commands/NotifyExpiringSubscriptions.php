<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\ProviderSubscriptionStatus;
use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Enums\SmsMessageType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Models\NetworkSmsSetting;
use App\Models\ProviderSubscription;
use App\Services\NotificationService;
use App\Services\Support\SMSGateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر تنبيه الشبكات بالاشتراكات التي أوشكت على الانتهاء عبر SMS والإشعارات.
 */
class NotifyExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:notify-expiring 
                            {--days=3 : Days threshold before expiry}
                            {--force : Force sending alerts}';

    protected $description = 'ينبه الشبكات التي تقترب اشتراكاتها من الانتهاء (عبر SMS والإشعارات) لتجديد الاشتراك في الوقت المناسب';

    public function handle(
        SMSGateService $smsGateService,
        ScheduledTaskRunRepositoryInterface $taskRunRepository,
        NotificationService $notificationService
    ): int {
        $now = Carbon::now('Asia/Aden');
        $days = (int) $this->option('days');
        $force = (bool) $this->option('force');
        $targetDate = $now->toDateString();

        $this->info("🚀 فحص الاشتراكات التي تنتهي خلال {$days} أيام...");

        $threshold = $now->copy()->addDays($days);

        // جلب الاشتراكات النشطة التي تنتهي بين الآن والموعد المحدد
        $expiringSubs = ProviderSubscription::with(['network', 'plan'])
            ->where('status', ProviderSubscriptionStatus::ACTIVE)
            ->whereNotNull('subscription_end_date')
            ->whereBetween('subscription_end_date', [$now, $threshold])
            ->get();

        if ($expiringSubs->isEmpty()) {
            $this->info('ℹ️ لا توجد اشتراكات تشارف على الانتهاء خلال هذه الفترة.');

            return 0;
        }

        $sentCount = 0;

        foreach ($expiringSubs as $sub) {
            $network = $sub->network;
            if (! $network) {
                continue;
            }

            $taskType = ScheduledTaskType::SUBSCRIPTIONS_NOTIFY_EXPIRING;
            if (! $force && $taskRunRepository->wasExecuted($taskType, $network->network_id, $targetDate)) {
                continue;
            }

            $endDate = Carbon::parse($sub->subscription_end_date, 'Asia/Aden')->format('Y-m-d');
            $planName = $sub->plan?->plan_name ?? 'الأساسية';
            $message = "⚠️ تنبيه اشتراك شبكة ({$network->network_name}):\n"
                ."باقة الاشتراك ({$planName}) ستنتهي بتاريخ: {$endDate}.\n"
                ."يرجى تجديد الاشتراك لضمان استمرارية الخدمات دون انقطاع.";

            // 1. فحص إعدادات SMS للشبكة وإرسال الرسالة إن كانت مفعلة
            $smsSetting = NetworkSmsSetting::where('network_id', $network->network_id)->first();
            $smsSent = false;

            if ($smsSetting?->is_enabled && $smsSetting->notify_subscription_expiry && $network->network_phone_number) {
                try {
                    $smsSent = $smsGateService->sendSms(
                        phoneNumbers: $network->network_phone_number,
                        message: $message,
                        priority: 1,
                        recipientId: $network->network_id,
                        messageType: SmsMessageType::SUBSCRIPTION_EXPIRY->value
                    );
                } catch (\Throwable $e) {
                    $this->warn("تعذر إرسال SMS لشبكة {$network->network_name}: ".$e->getMessage());
                }
            }

            // 2. تسجيل إشعار داخلي في النظام وإرسال push FCM تلقائياً
            $notificationService->sendDirect(
                userId: (int) ($network->approved_by ?? 1),
                type: NotificationType::WARNING,
                title: '⚠️ اقتراب انتهاء اشتراك الشبكة',
                message: $message
            );

            // 3. توثيق تنفيذ المهمة لليوم
            $taskRunRepository->recordRun(
                taskType: $taskType,
                networkId: $network->network_id,
                targetDate: $targetDate,
                status: ScheduledTaskStatus::SENT,
                extra: [
                    'end_date' => $endDate,
                    'sms_sent' => $smsSent,
                ]
            );

            $sentCount++;
            $this->line("  ✔ تم إرسال تنبيه انتهاء الاشتراك لشبكة: {$network->network_name}");
        }

        $this->info("✅ اكتملت المهمة: تم إرسال {$sentCount} تنبيهاً لاشتراكات تشارف على الانتهاء.");

        return 0;
    }
}
