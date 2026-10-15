<?php

namespace App\Console\Commands;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Enums\SmsMessageType;
use App\Interfaces\ScheduledTaskRunRepositoryInterface;
use App\Models\Expense;
use App\Models\Network;
use App\Models\NetworkSmsSetting;
use App\Models\SaleInvoice;
use App\Services\Support\SMSGateService;
use App\Services\SystemLoggerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر إرسال الملخص اليومي للمبيعات والمصروفات للشبكات المشتركة عبر SMS.
 */
class SendDailySummarySms extends Command
{
    protected $signature = 'reports:daily-summary-sms 
                            {--date= : Specific target date (YYYY-MM-DD)}
                            {--network= : Specific network ID}
                            {--force : Force sending even if already sent today}';

    protected $description = 'يرسل الملخص المالي اليومي للشبكات عبر SMSGate للشبكات المفعلة للميزة';

    public function handle(
        SMSGateService $smsGateService,
        ScheduledTaskRunRepositoryInterface $taskRunRepository
    ): int {
        $now = Carbon::now('Asia/Aden');
        $force = (bool) $this->option('force');
        $dateInput = $this->option('date');
        $networkFilter = $this->option('network');

        $targetDate = ! empty($dateInput)
            ? Carbon::parse($dateInput, 'Asia/Aden')->toDateString()
            : $now->toDateString();

        $this->info("🚀 بدء معالجة الملخص المالي اليومي لتاريخ: {$targetDate}...");

        // جلب الشبكات النشطة التي قامت بتفعيل تنبيه الملخص اليومي
        $settingsQuery = NetworkSmsSetting::with('network')
            ->where('is_enabled', true)
            ->where('notify_daily_summary', true);

        if ($networkFilter) {
            $settingsQuery->where('network_id', (int) $networkFilter);
        }

        $settings = $settingsQuery->get();

        if ($settings->isEmpty()) {
            $this->info('ℹ️ لا توجد شبكات مفعلة لخاصية الملخص اليومي حالياً.');

            return 0;
        }

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($settings as $setting) {
            $network = $setting->network;
            if (! $network || ! $network->network_phone_number) {
                continue;
            }

            // فحص هل تم إرسال الملخص اليومي لهذه الشبكة في هذا التاريخ لمنع التكرار (Idempotency)
            $taskType = ScheduledTaskType::REPORTS_DAILY_SMS;
            if (! $force && $taskRunRepository->wasExecuted($taskType, $network->network_id, $targetDate)) {
                $skippedCount++;

                continue;
            }

            // تجميع العمليات المالية للشبكة في هذا اليوم
            $salesTotal = (float) SaleInvoice::where('network_id', $network->network_id)
                ->whereDate('invoice_date', $targetDate)
                ->sum('invoice_total_amount');

            $invoicesCount = SaleInvoice::where('network_id', $network->network_id)
                ->whereDate('invoice_date', $targetDate)
                ->count();

            $expensesTotal = (float) Expense::where('network_id', $network->network_id)
                ->whereDate('expense_date', $targetDate)
                ->sum('expense_total_amount');

            $netIncome = $salesTotal - $expensesTotal;

            $salesFormatted = number_format($salesTotal, 2);
            $expensesFormatted = number_format($expensesTotal, 2);
            $netFormatted = number_format($netIncome, 2);
            $formattedDate = Carbon::parse($targetDate, 'Asia/Aden')->locale('ar')->translatedFormat('l، j F Y');

            $message = "📊 الملخص اليومي لشبكة ({$network->network_name})\n"
                ."📅 التاريخ: {$formattedDate}\n"
                ."🧾 عدد الفواتير: {$invoicesCount}\n"
                ."💰 إجمالي المبيعات: {$salesFormatted} ر.ي\n"
                ."💸 إجمالي المصروفات: {$expensesFormatted} ر.ي\n"
                ."📈 صافي الدخل: {$netFormatted} ر.ي";

            try {
                $success = $smsGateService->sendSms(
                    phoneNumbers: $network->network_phone_number,
                    message: $message,
                    priority: 2,
                    recipientId: $network->network_id,
                    messageType: SmsMessageType::DAILY_SUMMARY->value
                );

                $status = $success ? ScheduledTaskStatus::SENT : ScheduledTaskStatus::FAILED;

                $taskRunRepository->recordRun(
                    taskType: $taskType,
                    networkId: $network->network_id,
                    targetDate: $targetDate,
                    status: $status,
                    extra: [
                        'sales' => $salesTotal,
                        'expenses' => $expensesTotal,
                        'net' => $netIncome,
                        'invoices_count' => $invoicesCount,
                        'success' => $success,
                    ]
                );

                if ($status === ScheduledTaskStatus::SENT) {
                    $sentCount++;
                    $this->line("  ✔ تم إرسال ملخص شبكة: {$network->network_name}");
                } else {
                    $this->warn("  ✖ تعذر إرسال ملخص شبكة: {$network->network_name}");
                }
            } catch (\Throwable $e) {
                $taskRunRepository->recordRun(
                    taskType: $taskType,
                    networkId: $network->network_id,
                    targetDate: $targetDate,
                    status: ScheduledTaskStatus::FAILED,
                    extra: ['error' => $e->getMessage()]
                );

                $this->error("  ✖ خطأ في إرسال ملخص {$network->network_name}: ".$e->getMessage());
            }
        }

        $this->info("✅ اكتملت المهمة: تم إرسال {$sentCount} رسالة ملخص يومي، وتجاوز {$skippedCount} شبكة مرسلة مسبقاً.");

        return 0;
    }
}
