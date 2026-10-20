<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Enums\SmsMessageType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Models\NetworkSmsSetting;
use App\Models\PosWallet;
use App\Services\NotificationService;
use App\Services\Support\SMSGateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر فحص وتنبيه نقاط البيع والشبكات بانخفاض رصيد المحفظة عبر SMS والإشعارات.
 */
class CheckLowBalanceAlerts extends Command
{
    protected $signature = 'alerts:low-balance 
                            {--threshold= : Minimum balance threshold (defaults to system setting)}
                            {--force : Force sending even if already alerted today}';

    protected $description = 'يفحص أرصدة محافظ نقاط البيع وينبه الشبكة ونقطة البيع عند انخفاض الرصيد عن الحد الأدنى';

    public function handle(
        SMSGateService $smsGateService,
        ScheduledTaskRunRepositoryInterface $taskRunRepository,
        NotificationService $notificationService
    ): int {
        $now = Carbon::now('Asia/Aden');
        $defaultThreshold = (float) systemSetting('low_balance_alert_threshold', 5000.0);
        $threshold = $this->option('threshold') !== null ? (float) $this->option('threshold') : $defaultThreshold;
        $currencySymbol = systemSetting('currency_symbol', 'ر.ي');
        $force = (bool) $this->option('force');
        $targetDate = $now->toDateString();

        $this->info("🚀 فحص أرصدة المحافظ التي تقل عن {$threshold} {$currencySymbol}...");

        $lowWallets = PosWallet::with(['network', 'pos'])
            ->where('status', true)
            ->where('wallet_balance', '<=', $threshold)
            ->get();

        if ($lowWallets->isEmpty()) {
            $this->info('ℹ️ جميع أرصدة المحافظ أعلى من الحد الأدنى.');

            return 0;
        }

        $alertCount = 0;
        $taskType = ScheduledTaskType::ALERTS_LOW_BALANCE;
        $walletsByNetwork = $lowWallets->groupBy('network_id');

        foreach ($walletsByNetwork as $networkId => $wallets) {
            $network = $wallets->first()->network;
            if (! $network) {
                continue;
            }

            if (! $force && $taskRunRepository->wasExecuted($taskType, $network->network_id, $targetDate)) {
                continue;
            }

            $smsSetting = NetworkSmsSetting::where('network_id', $network->network_id)->first();
            $alertedWallets = [];

            foreach ($wallets as $wallet) {
                $pos = $wallet->pos;
                $balanceFormatted = number_format((float) $wallet->wallet_balance, 2);
                $posName = $pos?->pos_name ?? "نقطة بيع #{$wallet->pos_id}";

                $message = "⚠️ تنبيه انخفاض الرصيد:\n"
                    ."رصيد المحفظة لدى ({$posName}) في شبكة ({$network->network_name}) قد انخفض إلى: {$balanceFormatted} {$currencySymbol}.\n"
                    ."يرجى تغذية المحفظة لضمان استمرار مبيعات الكروت.";

                $smsSent = false;
                if ($smsSetting?->is_enabled && $smsSetting->notify_low_balance && $network->network_phone_number) {
                    try {
                        $smsSent = $smsGateService->sendSms(
                            phoneNumbers: $network->network_phone_number,
                            message: $message,
                            priority: 1,
                            recipientId: $network->network_id,
                            messageType: SmsMessageType::LOW_BALANCE->value
                        );
                    } catch (\Throwable $e) {
                        $this->warn("تعذر إرسال SMS لشبكة {$network->network_name}: ".$e->getMessage());
                    }
                }

                $notificationService->sendDirect(
                    userId: (int) $network->owner_id,
                    type: NotificationType::GENERAL_INFO,
                    push: true,
                    title: '⚠️ تنبيه انخفاض رصيد محفظة',
                    message: $message
                );

                $alertedWallets[] = [
                    'pos_id' => $wallet->pos_id,
                    'pos_name' => $posName,
                    'balance' => $wallet->wallet_balance,
                    'sms_sent' => $smsSent,
                ];

                $alertCount++;
                $this->line("  ✔ تم إرسال تنبيه انخفاض رصيد لـ {$posName} ({$network->network_name})");
            }

            $taskRunRepository->recordRun(
                taskType: $taskType,
                networkId: $network->network_id,
                targetDate: $targetDate,
                status: ScheduledTaskStatus::SENT,
                extra: [
                    'wallets_count' => count($alertedWallets),
                    'wallets' => $alertedWallets,
                ]
            );
        }

        $this->info("✅ اكتملت المهمة: تم إرسال {$alertCount} تنبيهاً لانخفاض الرصيد.");

        return 0;
    }
}
