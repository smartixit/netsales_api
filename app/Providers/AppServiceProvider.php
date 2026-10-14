<?php

namespace App\Providers;

use App\Services\Support\FileUploadService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! class_exists('App\Services\FileUploadService', false)) {
            class_alias(FileUploadService::class, 'App\Services\FileUploadService');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // إبقاء تسلسل التواريخ بصيغة Y-m-d H:i:s بالتوقيت المحلي الفعلي دون تحويلها إلى UTC أو إزاحتها زمنياً
        Carbon::serializeUsing(fn ($date) => $date->format('Y-m-d H:i:s'));

        // Force HTTPS for all links when the request already came over HTTPS or in production.
        // Otherwise keep the scheme as requested (e.g. http on a local/LAN dev server).
        if (app()->isProduction() || request()->isSecure() || request()->headers->get('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    /**
     * تعريف حدود معدل الطلبات (Rate Limits) بشكل متدرج حسب حساسية كل مجموعة.
     * يستخدم ذاكرة الملفات (file cache) لتجنب ضغط كتابة على قاعدة البيانات.
     */
    protected function configureRateLimiting(): void
    {
        // الحد العام — يُطبّق على كل طلب API كسقف شامل
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->user_id ?: $r->ip())
        );

        // تسجيل الدخول والتسجيل وإعادة تعيين كلمة المرور — حماية من Brute Force
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by('auth|'.$r->ip())
            ->response(fn () => response()->json([
                'success' => false,
                'message' => 'عدد محاولات تسجيل الدخول تجاوز الحد المسموح. حاول بعد دقيقة.',
                'data' => (object) [],
                'errors' => [],
            ], 429))
        );

        // طلبات OTP — أكثر تكلفة (SMS)
        RateLimiter::for('auth-otp', fn (Request $r) => Limit::perMinute(3)->by('otp|'.$r->ip())
            ->response(fn () => response()->json([
                'success' => false,
                'message' => 'تم تجاوز الحد المسموح لإرسال رموز التحقق. حاول بعد دقيقة.',
                'data' => (object) [],
                'errors' => [],
            ], 429))
        );

        // قراءة البيانات — الحد الطبيعي لمستخدم مصادق
        RateLimiter::for('api-read', fn (Request $r) => Limit::perMinute(120)->by('read|'.($r->user()?->user_id ?: $r->ip()))
        );

        // كتابة البيانات — إنشاء/تعديل/حذف
        RateLimiter::for('api-write', fn (Request $r) => Limit::perMinute(30)->by('write|'.($r->user()?->user_id ?: $r->ip()))
        );

        // البحث — يحمي من إرسال استعلامات بحث مفرطة
        RateLimiter::for('api-search', fn (Request $r) => Limit::perMinute(30)->by('search|'.($r->user()?->user_id ?: $r->ip()))
        );

        // التقارير — استعلامات مكلفة على قاعدة البيانات
        RateLimiter::for('api-reports', fn (Request $r) => Limit::perMinute(10)->by('reports|'.($r->user()?->user_id ?: $r->ip()))
        );

        // بيانات مرجعية عامة (أدوار، أذونات، عملات...)
        RateLimiter::for('api-public', fn (Request $r) => Limit::perMinute(60)->by('pub|'.$r->ip())
        );

        // رفع الملفات
        RateLimiter::for('api-upload', fn (Request $r) => Limit::perMinute(5)->by('upload|'.($r->user()?->user_id ?: $r->ip()))
        );
    }
}
