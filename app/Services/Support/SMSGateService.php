<?php

namespace App\Services\Support;

use App\Models\NetworkSmsSetting;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * خدمة إرسال الرسائل القصيرة عبر بوابة SMSGate (Enterprise SMSGate Integration Service).
 *
 * صُممت وفق معايير برمجية نظيفة وقابلة للتوسع:
 * 1. تهيئة مستقلة تعتمد على ملف الإعدادات config('services.smsgate') والبيئة.
 * 2. دعم بوابات وأجهزة مخصصة لكل شبكة (Tenant-Specific Gateway Override).
 * 3. فحص هرمي لسياسة التفعيل ومفاتيح أنواع الرسائل لكل شبكة قبل الاستهلاك.
 * 4. إدارة رموز Bearer Token والتجديد التلقائي عبر Refresh Token أو السقوط على Basic Auth.
 * 5. معالجة وتنسيق أرقام الهواتف اليمنية والدولية وفق معيار E.164.
 * 6. دعم المحاكاة الآمنة أثناء بيئة الاختبارات لتجنب تعليق الطلبات.
 * 7. تسجيل تفصيلي لعمليات الإرسال والأخطاء لأغراض التدقيق والمراقبة.
 */
class SMSGateService
{
    protected array $config;

    public function __construct(?array $customConfig = null)
    {
        $this->config = $customConfig ?? (array) config('services.smsgate', []);
    }

    /**
     * تنسيق أرقام الهواتف وفق معيار E.164 مع دعم خاص لأرقام شبكات الهاتف اليمنية (Yemen Mobile, YOU, Sabafon, Y).
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        // إزالة الفراغات والشرطات وأي رموز غير رقمية عدا علامة +
        $phone = preg_replace('/[^0-9+]/', '', $phoneNumber);

        if (empty($phone)) {
            return '';
        }

        if (str_starts_with($phone, '00')) {
            return '+'.substr($phone, 2);
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        // الأرقام اليمنية المكونة من 9 خانات تبدأ بـ 7 (مثل: 772957881)
        if (preg_match('/^7[0-9]{8}$/', $phone)) {
            return '+967'.$phone;
        }

        // الأرقام اليمنية التي تبدأ بصفر محلي (مثل: 0772957881)
        if (preg_match('/^07[0-9]{8}$/', $phone)) {
            return '+967'.substr($phone, 1);
        }

        return $phone;
    }

    /**
     * تنظيف وتصحيح رابط البوابة الأساسي.
     */
    public function cleanBaseUrl(?string $url): string
    {
        if (empty($url)) {
            return 'https://api.sms-gate.app';
        }

        $trimmed = rtrim(trim($url), '/');
        $cleaned = preg_replace('#/(3rdparty|mobile)(/v\d+)?(/.*)?$#i', '', $trimmed);

        return ! empty($cleaned) ? $cleaned : 'https://api.sms-gate.app';
    }

    /**
     * إرسال رسالة قصيرة لرقم هاتف واحد أو عدة أرقام مع دعم بوابات الشبكات وسياسات التفعيل.
     *
     * @param  string|array<string>  $phoneNumbers  الأرقام المستلمة
     * @param  string  $message  نص الرسالة
     * @param  int  $priority  الأولوية (0 عادي، 1 عالي)
     * @param  int  $ttl  فترة بقاء الرسالة بالثواني
     * @param  int|null  $recipientId  معرف الشبكة (لتطبيق سياستها واستخدام بوابتها الخاصة)
     * @param  bool  $bypassPolicy  تجاوز القيود (لرموز OTP ورسائل النظام العاجلة)
     * @param  string|null  $messageType  نوع الرسالة (receipt, payment, expense, etc.)
     */
    public function sendSms(
        string|array $phoneNumbers,
        string $message,
        int $priority = 0,
        int $ttl = 604800,
        ?int $recipientId = null,
        bool $bypassPolicy = false,
        ?string $messageType = null
    ): bool {
        // 0. فحص سياسة التفعيل: المفتاح العام ثم مفاتيح الشبكة والنوع المحدد
        if (! $bypassPolicy && ! $this->smsAllowedFor($recipientId, $messageType)) {
            Log::info('SMSGate Send Blocked: SMS disabled by policy.', [
                'network_id' => $recipientId,
                'message_type' => $messageType,
            ]);

            return false;
        }

        // في بيئة الاختبارات: محاكاة النجاح لتفادي استهلاك الرسائل والاتصال الخارجي
        if (app()->runningUnitTests()) {
            Log::info('SMSGate [Mocked]: Message dispatched to recipient(s).', [
                'type' => $messageType,
                'recipient_id' => $recipientId,
            ]);

            return true;
        }

        // 1. معالجة وتنسيق أرقام المستلمين
        $recipients = is_array($phoneNumbers) ? $phoneNumbers : [$phoneNumbers];
        $formattedRecipients = [];

        foreach ($recipients as $number) {
            $formatted = $this->formatPhoneNumber((string) $number);
            if (! empty($formatted)) {
                $formattedRecipients[] = $formatted;
            }
        }

        if (empty($formattedRecipients)) {
            Log::warning('SMSGate Send Skipped: No valid recipient phone numbers provided.');

            return false;
        }

        // 2. تحديد إعدادات البوابة والمالك (الشبكة أو النظام)
        [$effectiveConfig, $tokenOwnerType, $tokenOwnerId] = $this->resolveGatewaySettings($recipientId, $bypassPolicy, $messageType);

        // 3. ضمان توكن Bearer صالح دائماً وتجديده (عبر refresh_token أو السقوط على basic auth)
        $this->ensureValidToken($effectiveConfig, $tokenOwnerType, $tokenOwnerId);

        $baseUrl = $this->cleanBaseUrl($effectiveConfig['base_url'] ?? null);
        $token = $effectiveConfig['api_token'] ?? '';
        $deviceId = $effectiveConfig['device_id'] ?? null;
        $simNumber = (int) ($effectiveConfig['sim_number'] ?? 1);
        $skipValidation = (bool) ($effectiveConfig['skip_phone_validation'] ?? true);
        $deviceActiveWithin = (int) ($effectiveConfig['device_active_within'] ?? 0);

        if (empty($token)) {
            Log::warning('SMSGate Send Failed: API Token is not configured.', [
                'network_id' => $recipientId,
            ]);

            return false;
        }

        // 4. تجهيز الطلب والحمولة
        $endpoint = rtrim($baseUrl, '/').'/3rdparty/v1/messages';
        $queryParams = [
            'skipPhoneValidation' => $skipValidation ? 'true' : 'false',
            'deviceActiveWithin' => $deviceActiveWithin,
        ];

        $payload = [
            'textMessage' => [
                'text' => $message,
            ],
            'phoneNumbers' => array_values(array_unique($formattedRecipients)),
            'simNumber' => $simNumber,
            'ttl' => $ttl,
            'priority' => $priority,
        ];

        if (! empty($deviceId)) {
            $payload['deviceId'] = $deviceId;
        }

        // 5. التنفيذ عبر HTTP مع Bearer Token
        try {
            $response = Http::timeout(10)
                ->withQueryParameters($queryParams)
                ->withToken($token)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                Log::info('SMSGate SMS Dispatch Successful', [
                    'recipients_count' => count($formattedRecipients),
                    'message_type' => $messageType,
                    'recipient_id' => $recipientId,
                ]);

                return true;
            }

            Log::error('SMSGate HTTP Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'recipient_id' => $recipientId,
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('SMSGate Connection Exception: '.$e->getMessage(), [
                'endpoint' => $endpoint,
                'recipient_id' => $recipientId,
            ]);

            return false;
        }
    }

    /**
     * تحديد مصدر إعدادات البوابة: بوابة النظام العامة أم بوابة خاصة بالشبكة.
     *
     * @return array{0: array, 1: string, 2: int|null} [settings, tokenOwnerType, tokenOwnerId]
     */
    public function resolveGatewaySettings(?int $networkId, bool $bypassPolicy = false, ?string $messageType = null): array
    {
        // رموز OTP ورسائل الإدارة العامة والبث النظامي: تستخدم حصراً إعدادات بوابة النظام العامة
        if ($messageType === 'otp' || $messageType === 'admin_broadcast' || $networkId === null) {
            return [$this->config, 'system', null];
        }

        /** @var NetworkSmsSetting|null $networkSetting */
        $networkSetting = NetworkSmsSetting::where('network_id', $networkId)->first();

        // إذا كانت الشبكة قد فعّلت بوابة خاصة ولديها توكن أو بيانات مصادقة
        if ($networkSetting && $networkSetting->use_custom_gateway && (! empty($networkSetting->api_token) || ! empty($networkSetting->username))) {
            return [$networkSetting->toGatewayConfigArray(), 'network', $networkId];
        }

        // وراثة إعدادات بوابة النظام العامة
        return [$this->config, 'system', null];
    }

    /**
     * التحقق من سريان توكن الـ Bearer وتجديده تلقائياً عند انتهائه.
     */
    public function ensureValidToken(array &$settings, string $tokenOwnerType = 'system', ?int $tokenOwnerId = null): void
    {
        $token = $settings['api_token'] ?? null;
        $expiresAt = $settings['token_expires_at'] ?? null;

        // إذا كان التوكن موجوداً وتاريخ انتهائه في المستقبل، نستمر مباشرة
        if ($token && $expiresAt && strtotime($expiresAt) > time()) {
            return;
        }

        // إذا كان التوكن موجوداً ولكن لا يوجد تاريخ انتهاء محدد، نعتبره صالحاً
        if ($token && empty($expiresAt)) {
            return;
        }

        Log::info("SMSGate Bearer Token expired or missing. Attempting refresh for {$tokenOwnerType}...", [
            'owner_id' => $tokenOwnerId,
        ]);

        $this->refreshToken($settings, $tokenOwnerType, $tokenOwnerId);
    }

    /**
     * تجديد التوكن: المحاولة الأولى عبر refresh_token، والمحاولة الثانية السقوط على Basic Auth.
     */
    public function refreshToken(array &$settings, string $tokenOwnerType = 'system', ?int $tokenOwnerId = null): void
    {
        $baseUrl = $this->cleanBaseUrl($settings['base_url'] ?? null);
        $refreshToken = $settings['refresh_token'] ?? null;

        // المحاولة 1: التجديد عبر refresh_token
        if ($refreshToken) {
            try {
                $response = Http::timeout(10)
                    ->withToken($refreshToken)
                    ->post(rtrim($baseUrl, '/').'/3rdparty/v1/auth/token/refresh');

                if ($response->successful()) {
                    $data = $response->json();
                    $this->storeNewTokens($data, $settings, $tokenOwnerType, $tokenOwnerId);
                    Log::info("SMSGate Token refreshed successfully via refresh_token for {$tokenOwnerType}.");

                    return;
                }
                Log::warning('SMSGate refresh_token failed: '.$response->body());
            } catch (Exception $e) {
                Log::warning('SMSGate refresh_token exception: '.$e->getMessage());
            }
        }

        // المحاولة 2: السقوط على Basic Auth لإصدار زوج توكنات جديد
        $username = $settings['username'] ?? null;
        $password = $settings['password'] ?? null;

        if ($username && $password) {
            try {
                $response = Http::withBasicAuth($username, $password)
                    ->timeout(10)
                    ->post(rtrim($baseUrl, '/').'/3rdparty/v1/auth/token', [
                        'ttl' => 86400 * 365,
                        'scopes' => [
                            'messages:send', 'messages:read', 'messages:list',
                            'devices:list', 'settings:read', 'settings:write',
                            'tokens:manage',
                        ],
                    ]);

                if ($response->status() === 201 || $response->status() === 200) {
                    $data = $response->json();
                    $this->storeNewTokens($data, $settings, $tokenOwnerType, $tokenOwnerId);
                    Log::info("SMSGate Token re-issued successfully via basic auth fallback for {$tokenOwnerType}.");

                    return;
                }
                Log::error('SMSGate basic auth token re-issue failed: '.$response->body());
            } catch (Exception $e) {
                Log::error('SMSGate basic auth token re-issue exception: '.$e->getMessage());
            }
        }

        Log::error('SMSGate Unable to refresh or re-issue JWT token. SMS may fail.');
    }

    /**
     * حفظ التوكن الجديد في قاعدة البيانات للمالك المعني (الشبكة أو إعدادات النظام).
     */
    protected function storeNewTokens(array $data, array &$settings, string $tokenOwnerType = 'system', ?int $tokenOwnerId = null): void
    {
        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        $rawExpiresAt = $data['expires_at'] ?? null;

        $formattedExpiresAt = null;
        if (! empty($rawExpiresAt)) {
            try {
                $formattedExpiresAt = Carbon::parse($rawExpiresAt)->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $formattedExpiresAt = null;
            }
        }

        // تحديث الإعدادات الفعالة في الذاكرة لتكملة الإرسال اللحظي
        if ($accessToken) {
            $settings['api_token'] = $accessToken;
        }
        if ($refreshToken) {
            $settings['refresh_token'] = $refreshToken;
        }
        if ($formattedExpiresAt) {
            $settings['token_expires_at'] = $formattedExpiresAt;
        }

        // حفظ التوكن في قاعدة البيانات
        if ($tokenOwnerType === 'network' && $tokenOwnerId) {
            try {
                NetworkSmsSetting::where('network_id', $tokenOwnerId)->update([
                    'api_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_expires_at' => $formattedExpiresAt,
                ]);
            } catch (Exception $e) {
                Log::error('SMSGate failed to persist network token to DB: '.$e->getMessage());
            }
        } else {
            try {
                if ($accessToken) {
                    SystemSetting::updateOrCreate(
                        ['setting_key' => 'smsgate_api_token'],
                        ['setting_value' => $accessToken, 'setting_category' => 'integration']
                    );
                }
                if ($refreshToken) {
                    SystemSetting::updateOrCreate(
                        ['setting_key' => 'smsgate_refresh_token'],
                        ['setting_value' => $refreshToken, 'setting_category' => 'integration']
                    );
                }
                if ($formattedExpiresAt) {
                    SystemSetting::updateOrCreate(
                        ['setting_key' => 'smsgate_token_expires_at'],
                        ['setting_value' => $formattedExpiresAt, 'setting_category' => 'integration']
                    );
                }
            } catch (Exception $e) {
                Log::error('SMSGate failed to persist system token to DB: '.$e->getMessage());
            }
        }
    }

    /**
     * إرسال رمز التحقق (OTP) العاجل بأولوية قصوى.
     */
    public function sendOtp(string $phoneNumber, string $code, ?int $recipientId = null): bool
    {
        $message = "رمز التحقق الخاص بك في نظام مبيعات الشبكة هو: {$code}\nلا تشارك هذا الرمز مع أي شخص حفاظاً على أمان حسابك.";

        return $this->sendSms(
            phoneNumbers: $phoneNumber,
            message: $message,
            priority: 1,
            ttl: 300,
            recipientId: $recipientId,
            bypassPolicy: true,
            messageType: 'otp'
        );
    }

    /**
     * إرسال رسالة نصية مباشرة لمستخدم محدد بالاعتماد على رقم هاتفه المسجل.
     */
    public function sendToUser(int $userId, string $title, string $body, int $priority = 0, ?int $networkId = null, ?string $messageType = null): bool
    {
        /** @var User|null $user */
        $user = User::find($userId);
        if (! $user) {
            Log::error("SMSGate Send Error: User with ID {$userId} not found.");

            return false;
        }

        if (empty($user->user_phone_number)) {
            Log::warning("SMSGate Send Skipped: User {$user->user_full_name} (ID: {$userId}) has no registered phone number.");

            return false;
        }

        $message = "{$title}\n{$body}";

        return $this->sendSms(
            phoneNumbers: $user->user_phone_number,
            message: $message,
            priority: $priority,
            recipientId: $networkId,
            messageType: $messageType
        );
    }

    /**
     * التحقق من سياسة تفعيل الإرسال الهرمية للشبكة ونوع الرسالة.
     */
    public function smsAllowedFor(?int $networkId, ?string $messageType = null): bool
    {
        // رموز OTP ورسائل النظام العامة بدون شبكة محددة تمر مباشرة
        if ($messageType === 'otp' || $networkId === null) {
            return true;
        }

        /** @var NetworkSmsSetting|null $setting */
        $setting = NetworkSmsSetting::where('network_id', $networkId)->first();
        if ($setting !== null) {
            return $setting->isMessageTypeEnabled($messageType);
        }

        return true;
    }
}
