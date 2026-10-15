<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\ScheduledTaskStatus;
use App\Enums\SmsMessageType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Models\NetworkSmsSetting;
use App\Models\Notification;
use App\Models\PosWallet;
use App\Models\ScheduledTaskRun;
use App\Services\Support\SMSGateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر فحص وتنبيه نقاط البيع والشبكات بانخفاض رصيد المحفظة عبر SMS والإشعارات.
 */
class CheckLowBalanceAlerts extends Command
{
    protected $signature = 'alerts:low-balance 
                            {--threshold=5000 : Minimum balance threshold}
                            {--force : Force sending even if already alerted today}';

    protected $description = 'يفحص أرصدة محافظ نقاط البيع وينبه الشبكة ونقطة البيع عند انخفاض الرصيد عن الحد الأدنى';

    public function handle(
        SMSGateService $smsGateService,
        ScheduledTaskRunRepositoryInterface $taskRunRepository
    ): int {
        $now = Carbon::now('Asia/Aden');
        $threshold = (float) $this->option('threshold');
        $force = (bool) $this->option('force');
        $targetDate = $now->toDateString();

        $this->info("🚀 فحص أرصدة المحافظ التي تقل عن {$threshold} ر.ي...");

        $lowWallets = PosWallet::with(['network', 'pos'])
            ->where('status', true)
            ->where('wallet_balance', '<=', $threshold)
            ->get();

        if ($lowWallets->isEmpty()) {
            $this->info('ℹ️ جميع أرصدة المحافظ أعلى من الحد الأدنى.');

            return 0;
        }

        $alertCount = 0;

        foreach ($lowWallets as $wallet) {
            $network = $wallet->network;
            $pos = $wallet->pos;
            if (! $network) {
                continue;
            }

            $taskType = SmsMessageType::LOW_BALANCE->value;
            $taskKey = "{$taskType}_pos_{$wallet->pos_id}";

            if (! $force && $taskRunRepository->wasExecuted($taskKey, $network->network_id, $targetDate)) {
                continue;
            }

            $balanceFormatted = number_format((float) $wallet->wallet_balance, 2);
            $posName = $pos?->pos_name ?? "نقطة بيع #{$wallet->pos_id}";

            $message = "⚠️ تنبيه انخفاض الرصيد:\n"
                ."رصيد المحفظة لدى ({$posName}) في شبكة ({$network->network_name}) قد انخفض إلى: {$balanceFormatted} ر.ي.\n"
                ."يرجى تغذية المحفظة لضمان استمرار مبيعات الكروت.";

            $smsSetting = NetworkSmsSetting::where('network_id', $network->network_id)->first();
            $smsSent = false;

            if ($smsSetting?->is_enabled && $smsSetting->notify_low_balance && $network->network_phone_number) {
                try {
                    $smsSent = $smsGateService->sendSms(
                        phoneNumbers: $network->network_phone_number,
                        message: $message,
                        priority: 1,
                        recipientId: $network->network_id,
                        messageType: $taskType
                    );
                } catch (\Throwable $e) {
                    $this->warn("تعذر إرسال SMS لشبكة {$network->network_name}: ".$e->getMessage());
                }
            }

            Notification::create([
                'user_id' => $network->approved_by ?? 1,
                'notification_type' => NotificationType::WARNING,
                'notification_title' => '⚠️ تنبيه انخفاض رصيد محفظة',
                'notification_message' => $message,
                'is_read' => false,
            ]);

            $taskRunRepository->recordRun(
                taskType: $taskKey,
                networkId: $network->network_id,
                targetDate: $targetDate,
                status: ScheduledTaskStatus::STATUS_SENT->value,
                extra: [
                    'pos_id' => $wallet->pos_id,
                    'balance' => $wallet->wallet_balance,
                    'sms_sent' => $smsSent,
                ]
            );

            $alertCount++;
            $this->line("  ✔ تم إرسال تنبيه انخفاض رصيد لـ {$posName} ({$network->network_name})");
        }

        $this->info("✅ اكتملت المهمة: تم إرسال {$alertCount} تنبيهاً لانخفاض الرصيد.");

        return 0;
    }
}
