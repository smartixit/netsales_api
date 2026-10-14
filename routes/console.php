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
        // فحص وتفعيل أي اشتراك مجدول في الانتظار تلقائياً عبر الخدمة
        $subscriptionService->getActiveForNetwork($sub->network_id);
        $count++;
    }

    $this->info("تم فحص ومعالجة {$count} اشتراكاً منتهي الصلاحية وتحديث حالتها بنجاح.");
})->purpose('فحص وإنهاء الاشتراكات المنتهية وتفعيل الباقات المجدولة في الخلفية');

Schedule::command('subscriptions:expire-outdated')->hourly();
