<?php

use App\Services\Billing\BillingDunningService;
use App\Services\Billing\BillingRenewalService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:process-renewals', function (BillingRenewalService $renewals) {
    $result = $renewals->processDueSubscriptions((int) config('billing.renewal_batch_size', 100));
    $this->info("تم إنشاء {$result['renewal_invoices_created']} فاتورة تجديد وإنهاء {$result['subscriptions_cancelled']} اشتراكاً عند نهاية الفترة.");
})->purpose('إصدار فواتير التجديد وتحويل التجارب المنتهية وفق دورة الاشتراك الجديدة');

Artisan::command('billing:process-dunning', function (BillingDunningService $dunning) {
    $result = $dunning->process((int) config('billing.dunning_batch_size', 100));
    $this->info("تم إدراج {$result['reminders_queued']} تذكيراً بالفواتير و{$result['renewal_alerts_queued']} تنبيهاً بالتجديد في Outbox وتعليق {$result['subscriptions_suspended']} اشتراكاً بعد انتهاء السماح.");
})->purpose('معالجة التذكيرات المالية وفترات السماح والتنبيهات');

// تعريف دوري واحد فقط. جميع المهام الفعلية ومواعيدها موجودة في ScheduledTaskType.
// يعمل هذا المشغل مع Cron عند توفره، ويعمل محرك النشاط من Middleware عند غيابه.
Schedule::command('schedule:catchup')
    ->everyMinute()
    ->timezone('Asia/Aden')
    ->withoutOverlapping(35);
