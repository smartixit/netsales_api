<?php

namespace App\Http\Middleware;

use App\Services\SmartSchedulerCatchupService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * يشغّل نبضة محدودة لمحرك الجدولة بعد إرسال استجابة API عند غياب Cron.
 */
class RunSchedulerCatchupOnActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($response->getStatusCode() >= 500 || $request->is('up')) {
            return;
        }

        try {
            if (! Cache::add('smart_scheduler_activity_tick', now('Asia/Aden')->timestamp, 30)) {
                return;
            }

            app(SmartSchedulerCatchupService::class)->runActivityTick();
        } catch (Throwable $exception) {
            Log::warning('تعذر تشغيل نبضة الجدولة بعد طلب API: '.$exception->getMessage(), [
                'path' => $request->path(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
