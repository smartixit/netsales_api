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
})->purpose('معالجة التذكيرات المالية وتعليق الاشتراكات المتأخرة بعد فترة السماح');

// ═══════════════════════════════════════════════════════════════════════
// جدولة المهام الدورية وفق توقيت صنعاء (Asia/Aden Schedule Registry)
// ═══════════════════════════════════════════════════════════════════════

// 1. معالجة فواتير التجديد والتحويل من التجربة كل 15 دقيقة
Schedule::command('billing:process-renewals')->everyFifteenMinutes()->withoutOverlapping()->timezone('Asia/Aden');

// 2. معالجة التذكيرات المالية وفترات السماح كل 15 دقيقة
Schedule::command('billing:process-dunning')->everyFifteenMinutes()->withoutOverlapping()->timezone('Asia/Aden');

// 3. إرسال الملخص المالي اليومي للشبكات عبر SMS (يومياً الساعة 8 مساءً)
Schedule::command('reports:daily-summary-sms')->dailyAt('20:00')->timezone('Asia/Aden');

// 4. إرسال الملخص المالي الشهري للشبكات عبر SMS (اليوم الأول من كل شهر الساعة 8 صباحاً)
Schedule::command('reports:monthly-summary-sms')->monthlyOn(1, '08:00')->timezone('Asia/Aden');

// 5. فحص وتنبيه انخفاض أرصدة محافظ نقاط البيع (مرتين يومياً: 9 صباحاً و 3 عصراً)
Schedule::command('alerts:low-balance')->twiceDaily(9, 15)->timezone('Asia/Aden');

// 6. النسخ الاحتياطي التلقائي الشامل للنظام إلى Google Drive (يومياً الساعة 3 فجراً)
Schedule::command('backup:auto')->dailyAt('03:00')->timezone('Asia/Aden');

// 7. تنظيف جلسات المستخدمين المنتهية والمبطلة القديمة (يومياً الساعة 2 فجراً)
Schedule::command('cleanup:user-sessions')->dailyAt('02:00')->timezone('Asia/Aden');

// 8. تنظيف رموز التحقق المؤقتة (OTP) المنتهية أو المستعملة (يومياً الساعة 2:15 فجراً)
Schedule::command('cleanup:expired-otp')->dailyAt('02:15')->timezone('Asia/Aden');

// 9. رفع الحظر الأمني المنتهي تلقائياً عن المستخدمين والأجهزة (يومياً الساعة 2:30 فجراً)
Schedule::command('security:lift-expired-bans')->dailyAt('02:30')->timezone('Asia/Aden');

// 9. تنظيف وأرشفة سجلات النظام القديمة الأقدم من 60 يوماً (أسبوعياً فجر الأحد الساعة 4 فجراً)
Schedule::command('system:prune-logs')->weeklyOn(0, '04:00')->timezone('Asia/Aden');

// 10. محرك الاستدراك والتعويض الذكي للمهام الفائتة (يعمل كل 15 دقيقة)
Schedule::command('schedule:catchup')->everyFifteenMinutes();

// 11. معالجة أحداث صندوق الإرسال اللحظي المالي (Transactional Outbox Processor كل دقيقة)
Schedule::command('outbox:process')->everyMinute()->withoutOverlapping();

// 11. تصريف ومعالجة طوابير النظام تلقائياً عند تشغيل الجدولة القياسية
if (config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
        ->everyMinute()
        ->withoutOverlapping();
}
