<?php

use App\Enums\ProviderSubscriptionStatus;
use App\Models\ProviderSubscription;
use App\Services\ProviderSubscriptionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:expire-outdated', function (ProviderSubscriptionService $subscriptionService) {
    $now = now('Asia/Aden');
    $expiredSubs = ProviderSubscription::where('status', ProviderSubscriptionStatus::ACTIVE)
        ->whereNotNull('subscription_end_date')
        ->where('subscription_end_date', '<=', $now)
        ->get();

    $count = 0;
    foreach ($expiredSubs as $sub) {
        $subscriptionService->getActiveForNetwork($sub->network_id);
        $count++;
    }

    $this->info("تم فحص ومعالجة {$count} اشتراكاً منتهي الصلاحية وتحديث حالتها بنجاح.");
})->purpose('فحص وإنهاء الاشتراكات المنتهية وتفعيل الباقات المجدولة في الخلفية');

// ═══════════════════════════════════════════════════════════════════════
// جدولة المهام الدورية وفق توقيت صنعاء (Asia/Aden Schedule Registry)
// ═══════════════════════════════════════════════════════════════════════

// 1. فحص وإنهاء اشتراكات الشبكات المنتهية كل ساعة
Schedule::command('subscriptions:expire-outdated')->hourly();

// 2. تنبيه الشبكات باقتراب موعد انتهاء الاشتراك (يومياً الساعة 10 صباحاً)
Schedule::command('subscriptions:notify-expiring')->dailyAt('10:00')->timezone('Asia/Aden');

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

// 11. تصريف ومعالجة طوابير النظام تلقائياً في الخلفية (Self-Healing Queue Worker للاستضافات المشتركة)
if (! config('queue.worker_enabled', false) && config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
        ->everyMinute()
        ->withoutOverlapping();
}

