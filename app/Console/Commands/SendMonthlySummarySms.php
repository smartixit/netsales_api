<?php

namespace App\Console\Commands;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Enums\SmsMessageType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Models\Network;
use App\Models\NetworkSmsSetting;
use App\Services\Support\SMSGateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر إرسال الملخص الشهري للمبيعات والمصروفات للشبكات المشتركة عبر SMS.
 */
class SendMonthlySummarySms extends Command
{
    protected $signature = 'reports:monthly-summary-sms 
                            {--month= : Specific month (YYYY-MM)}
                            {--network= : Specific network ID}
                            {--force : Force sending even if already sent this month}';

    protected $description = 'يرسل الملخص المالي الشهري الشامل للشبكات المشتركة عبر SMSGate';

    public function handle(
        SMSGateService $smsGateService,
        ScheduledTaskRunRepositoryInterface $taskRunRepository
    ): int {
        $now = Carbon::now('Asia/Aden');
        $force = (bool) $this->option('force');
        $monthInput = $this->option('month');
        $networkFilter = $this->option('network');

        $targetMonthDate = ! empty($monthInput)
            ? Carbon::parse($monthInput.'-01', 'Asia/Aden')
            : $now->copy()->startOfMonth();

        $yearMonth = $targetMonthDate->format('Y-m');
        $targetDate = $targetMonthDate->toDateString();

        $this->info("🚀 بدء معالجة الملخص المالي الشهري لشهر: {$yearMonth}...");

        $settingsQuery = NetworkSmsSetting::with('network')
            ->active()
            ->where('notify_monthly_summary', true)
            ->whereHas('network', fn ($q) => $q->active());

        if ($networkFilter) {
            $settingsQuery->where('network_id', (int) $networkFilter);
        }

        $settings = $settingsQuery->get();

        if ($settings->isEmpty()) {
            $this->info('ℹ️ لا توجد شبكات مفعّلة لخاصية الملخص الشهري حالياً.');

            return 0;
        }

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($settings as $setting) {
            $network = $setting->network;
            if (! $network || ! $network->network_phone_number) {
                continue;
            }

            $taskType = ScheduledTaskType::REPORTS_MONTHLY_SMS;
            if (! $force && $taskRunRepository->wasExecuted($taskType, $network->network_id, $targetDate)) {
                $skippedCount++;

                continue;
            }

            // تجميع الشهر المستهدف عبر علاقات Eloquent
            $startOfMonth = $targetMonthDate->copy()->startOfMonth();
            $endOfMonth = $targetMonthDate->copy()->endOfMonth();

            $salesQuery = $network->saleInvoices()->whereBetween('invoice_date', [$startOfMonth, $endOfMonth]);

            $salesTotal = (float) (clone $salesQuery)->sum('invoice_total_amount');
            $cashCollected = (float) (clone $salesQuery)->sum('paid_amount');
            $creditRemaining = (float) (clone $salesQuery)->sum('remaining_amount');
            $invoicesCount = (clone $salesQuery)->count();

            $expensesTotal = (float) $network->expenses()
                ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
                ->sum('expense_total_amount');

            $netCash = $cashCollected - $expensesTotal;

            $salesFormatted = number_format($salesTotal, 2);
            $cashFormatted = number_format($cashCollected, 2);
            $creditFormatted = number_format($creditRemaining, 2);
            $expensesFormatted = number_format($expensesTotal, 2);
            $netCashFormatted = number_format($netCash, 2);
            $formattedMonth = $targetMonthDate->locale('ar')->translatedFormat('F Y');

            $message = "📊 الملخص الشهري لشبكة ({$network->network_name})\n"
                ."📅 شهر: {$formattedMonth}\n"
                ."🧾 إجمالي الفواتير: {$invoicesCount}\n"
                ."💰 مبيعات الشهر: {$salesFormatted} ر.ي\n"
                ."💵 النقد المحصل: {$cashFormatted} ر.ي\n"
                ."⏳ الآجل (المتبقي): {$creditFormatted} ر.ي\n"
                ."💸 مصروفات الشهر: {$expensesFormatted} ر.ي\n"
                ."📈 صافي النقد الشهري: {$netCashFormatted} ر.ي";

            try {
                $success = $smsGateService->sendSms(
                    phoneNumbers: $network->network_phone_number,
                    message: $message,
                    priority: 2,
                    recipientId: $network->network_id,
                    messageType: SmsMessageType::MONTHLY_SUMMARY->value
                );

                $status = $success ? ScheduledTaskStatus::SENT : ScheduledTaskStatus::FAILED;

                $taskRunRepository->recordRun(
                    taskType: $taskType,
                    networkId: $network->network_id,
                    targetDate: $targetDate,
                    status: $status,
                    extra: [
                        'year_month' => $yearMonth,
                        'sales' => $salesTotal,
                        'cash_collected' => $cashCollected,
                        'credit_remaining' => $creditRemaining,
                        'expenses' => $expensesTotal,
                        'net_cash' => $netCash,
                        'invoices_count' => $invoicesCount,
                    ]
                );

                if ($status === ScheduledTaskStatus::SENT) {
                    $sentCount++;
                    $this->line("  ✔ تم إرسال ملخص الشهر لشبكة: {$network->network_name}");
                }
            } catch (\Throwable $e) {
                $taskRunRepository->recordRun(
                    taskType: $taskType,
                    networkId: $network->network_id,
                    targetDate: $targetDate,
                    status: ScheduledTaskStatus::FAILED,
                    extra: ['error' => $e->getMessage()]
                );
            }
        }

        $this->info("✅ اكتملت المهمة: تم إرسال {$sentCount} رسالة ملخص شهري، وتجاوز {$skippedCount} شبكة.");

        return 0;
    }
}
