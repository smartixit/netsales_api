<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingRevenueRecognitionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class ProcessBillingRevenueRecognition extends Command
{
    protected $signature = 'billing:recognize-revenue {--limit=100 : Maximum schedules per run} {--date= : Recognition through date (YYYY-MM-DD)}';

    protected $description = 'ترحيل الإيراد المؤجل المكتسب حتى تاريخ التشغيل وفق مدد الخدمة المسجلة';

    public function handle(BillingRevenueRecognitionService $recognition): int
    {
        try {
            $asOf = $this->option('date')
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'), 'Asia/Aden')
                : CarbonImmutable::now('Asia/Aden');
            if (! $asOf) {
                $this->error('صيغة التاريخ غير صالحة؛ استخدم YYYY-MM-DD.');

                return self::INVALID;
            }

            $result = $recognition->process((int) $this->option('limit'), $asOf);
            $this->info("تمت معالجة {$result['processed']} جدول اعتراف بالإيراد.");

            foreach ($result['amount_by_currency'] as $currencyId => $amountMinor) {
                $this->line("Currency #{$currencyId}: {$amountMinor} minor units recognized.");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('تعذرت معالجة الاعتراف بالإيراد: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
