<?php

namespace App\Console\Commands;

use App\Enums\ScheduledTaskStatus;
use App\Enums\SmsMessageType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Models\Expense;
use App\Models\NetworkSmsSetting;
use App\Models\SaleInvoice;
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
            ->where('is_enabled', true)
            ->where('notify_monthly_summary', true);

        if ($networkFilter) {
            $settingsQuery->where('network_id', (int) $networkFilter);
        }

        $settings = $settingsQuery->get();

        if ($settings->isEmpty()) {
            $this->info('ℹ️ لا توجد شبكات مفعلة لخاصية الملخص الشهري حالياً.');

            return 0;
        }

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($settings as $setting) {
            $network = $setting->network;
            if (! $network || ! $network->network_phone_number) {
                continue;
            }

            $taskType = SmsMessageType::MONTHLY_SUMMARY->value;
            if (! $force && $taskRunRepository->wasExecuted($taskType, $network->network_id, $targetDate)) {
                $skippedCount++;

                continue;
            }

            // تجميع الشهر المستهدف
            $startOfMonth = $targetMonthDate->copy()->startOfMonth();
            $endOfMonth = $targetMonthDate->copy()->endOfMonth();

            $salesTotal = (float) SaleInvoice::where('network_id', $network->network_id)
                ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
                ->sum('invoice_total_amount');

            $invoicesCount = SaleInvoice::where('network_id', $network->network_id)
                ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
                ->count();

            $expensesTotal = (float) Expense::where('network_id', $network->network_id)
                ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
                ->sum('expense_total_amount');

            $netIncome = $salesTotal - $expensesTotal;

            $salesFormatted = number_format($salesTotal, 2);
            $expensesFormatted = number_format($expensesTotal, 2);
            $netFormatted = number_format($netIncome, 2);

            $message = "📊 الملخص الشهري لشبكة ({$network->network_name})\n"
                ."📅 شهر: {$yearMonth}\n"
                ."🧾 إجمالي الفواتير: {$invoicesCount}\n"
                ."💰 مبيعات الشهر: {$salesFormatted} ر.ي\n"
                ."💸 مصروفات الشهر: {$expensesFormatted} ر.ي\n"
                ."📈 صافي الربح: {$netFormatted} ر.ي";

            try {
                $success = $smsGateService->sendSms(
                    phoneNumbers: $network->network_phone_number,
                    message: $message,
                    priority: 2,
                    recipientId: $network->network_id,
                    messageType: $taskType
                );

                $status = $success
                    ? ScheduledTaskStatus::STATUS_SENT->value
                    : ScheduledTaskStatus::STATUS_FAILED->value;

                $taskRunRepository->recordRun(
                    taskType: $taskType,
                    networkId: $network->network_id,
                    targetDate: $targetDate,
                    status: $status,
                    extra: [
                        'year_month' => $yearMonth,
                        'sales' => $salesTotal,
                        'expenses' => $expensesTotal,
                        'net' => $netIncome,
                        'invoices_count' => $invoicesCount,
                    ]
                );

                if ($status === ScheduledTaskStatus::STATUS_SENT->value) {
                    $sentCount++;
                    $this->line("  ✔ تم إرسال ملخص الشهر لشبكة: {$network->network_name}");
                }
            } catch (\Throwable $e) {
                $taskRunRepository->recordRun(
                    taskType: $taskType,
                    networkId: $network->network_id,
                    targetDate: $targetDate,
                    status: ScheduledTaskStatus::STATUS_FAILED->value,
                    extra: ['error' => $e->getMessage()]
                );
            }
        }

        $this->info("✅ اكتملت المهمة: تم إرسال {$sentCount} رسالة ملخص شهري، وتجاوز {$skippedCount} شبكة.");

        return 0;
    }
}
