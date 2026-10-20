<?php

namespace App\Services\Support;

use App\Interfaces\UserDeviceRepositoryInterface;
use App\Services\SystemSettingService;
use Exception;
use Google\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * خدمة إرسال الإشعارات الفورية السحابية (Firebase Cloud Messaging - HTTP v1 API).
 *
 * تعتمد على البروتوكول الأحدث FCM HTTP v1 باستخدام OAuth2 Bearer Tokens المعتمدة
 * من ملف حساب الخدمة المخصص (Google Service Account JSON).
 */
class FcmService
{
    protected const FCM_ENDPOINT_TEMPLATE = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    protected const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    protected const TOKEN_CACHE_KEY = 'fcm_v1_access_token';

    protected HttpClient $httpClient;
    protected SystemSettingService $systemSettingService;

    public function __construct(
        protected UserDeviceRepositoryInterface $userDeviceRepository,
        ?SystemSettingService $systemSettingService = null,
        ?HttpClient $httpClient = null
    ) {
        $this->systemSettingService = $systemSettingService ?? app(SystemSettingService::class);
        $this->httpClient = $httpClient ?? new HttpClient(['timeout' => 10.0]);
    }

    /**
     * إرسال إشعار فوري لجميع الأجهزة النشطة المرتبطة بمستخدم محدد.
     *
     * @param array<string, mixed> $data
     * @return array{sent: int, failed: int, total: int}
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        ?string $image = null
    ): array {
        $tokens = $this->userDeviceRepository->getActiveFcmTokensForUser($userId);

        if (empty($tokens)) {
            Log::info("FcmService: No active FCM tokens found for user [{$userId}].");

            return ['sent' => 0, 'failed' => 0, 'total' => 0];
        }

        return $this->sendToTokens($tokens, $title, $body, $data, $image);
    }

    /**
     * إرسال إشعار فوري لمجموعة رموز أجهزة (Tokens).
     *
     * @param array<int, string> $tokens
     * @param array<string, mixed> $data
     * @return array{sent: int, failed: int, total: int}
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        ?string $image = null
    ): array {
        $sent = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            $success = $this->sendToToken($token, $title, $body, $data, $image);
            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($tokens),
        ];
    }

    /**
     * إرسال إشعار فوري لجهاز فردي عبر رمزه (FCM Device Token).
     *
     * @param array<string, mixed> $data
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
        ?string $image = null
    ): bool {
        if (! $this->isEnabled()) {
            Log::info('FcmService: Firebase is disabled or not configured in environment.');

            return false;
        }

        if (app()->runningUnitTests()) {
            Log::info('FcmService [Mocked]: Push notification sent to token.', [
                'token' => substr($token, 0, 15).'...',
                'title' => $title,
            ]);

            return true;
        }

        $projectId = $this->getProjectId();
        $accessToken = $this->getAccessToken();

        if (! $projectId || ! $accessToken) {
            Log::warning('FcmService: Missing project ID or failed to acquire OAuth2 access token.');

            return false;
        }

        $url = sprintf($this->getEndpointTemplate(), $projectId);
        $payload = $this->buildMessagePayload($token, $title, $body, $data, $image);

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json; UTF-8',
                ],
                'json' => ['message' => $payload],
            ]);

            return $response->getStatusCode() === 200;
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();
            $responseBody = (string) $e->getResponse()?->getBody();

            Log::error("FcmService: HTTP {$statusCode} client error sending push notification.", [
                'response' => $responseBody,
                'token' => substr($token, 0, 15).'...',
            ]);

            // التحقق مما إذا كان الرمز منتهي الصلاحية أو غير مسجل لإلغائه وتنظيف جدول الأجهزة
            if ($this->isUnregisteredTokenError($statusCode, $responseBody)) {
                $this->userDeviceRepository->invalidateFcmToken($token);
                Log::info("FcmService: Invalidated dead FCM token [".substr($token, 0, 15)."...] from user_devices.");
            }

            return false;
        } catch (GuzzleException|Exception $e) {
            Log::error('FcmService: Unexpected error sending push notification: '.$e->getMessage(), [
                'token' => substr($token, 0, 15).'...',
            ]);

            return false;
        }
    }

    /**
     * بناء هيكل الرسالة المتوافق مع معايير FCM HTTP v1.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function buildMessagePayload(
        string $token,
        string $title,
        string $body,
        array $data = [],
        ?string $image = null
    ): array {
        // تحويل كافة قيم مصفوفة البيانات إلى نصوص وفقاً لاشتراطات FCM HTTP v1
        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        // تضمين إجراء النقر الموحد لتطبيقات فلاتر ما لم يتم تخصيصه مسبقاً
        if (! isset($stringData['click_action'])) {
            $stringData['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
        }

        $notification = [
            'title' => $title,
            'body' => $body,
        ];

        if ($image) {
            $notification['image'] = $image;
        }

        $channelId = $this->systemSettingService->getString('firebase_android_channel_id', 'netsales_high_importance');

        $payload = [
            'token' => $token,
            'notification' => $notification,
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'channel_id' => $channelId,
                    'sound' => 'default',
                    'default_sound' => true,
                    'notification_priority' => 'PRIORITY_HIGH',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'sound' => 'default',
                        'badge' => 1,
                        'content-available' => 1,
                    ],
                ],
            ],
            'data' => $stringData,
        ];

        return $payload;
    }

    /**
     * الحصول على قالب رابط إرسال رسائل فايربيس
     */
    protected function getEndpointTemplate(): string
    {
        return $this->systemSettingService->getString('firebase_endpoint_template', self::FCM_ENDPOINT_TEMPLATE);
    }

    /**
     * نطاق المصادقة لرسائل فايربيس
     */
    protected function getFcmScope(): string
    {
        return $this->systemSettingService->getString('firebase_scope', self::FCM_SCOPE);
    }

    /**
     * مفتاح تخزين توكن فايربيس في الكاش
     */
    protected function getTokenCacheKey(): string
    {
        return $this->systemSettingService->getString('firebase_token_cache_key', self::TOKEN_CACHE_KEY);
    }

    /**
     * مسار ملف شهادة الخدمة لفايربيس مع دعم القراءة من جدول الإعدادات أو .env
     */
    protected function getCredentialsPath(): string
    {
        $custom = $this->systemSettingService->getString('firebase_credentials_path');
        if (! empty($custom)) {
            return str_starts_with($custom, '/') || preg_match('/^[a-zA-Z]:\\\\/', $custom) ? $custom : base_path($custom);
        }

        $configPath = (string) config('services.firebase.credentials_path', storage_path('app/firebase-service-account.json'));

        return str_starts_with($configPath, '/') || preg_match('/^[a-zA-Z]:\\\\/', $configPath) ? $configPath : base_path($configPath);
    }

    /**
     * الحصول على رمز الوصول OAuth2 الصالح مع التخزين المؤقت في الكاش لمدة 50 دقيقة.
     */
    protected function getAccessToken(): ?string
    {
        $cacheKey = $this->getTokenCacheKey();

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $credentialsPath = $this->getCredentialsPath();

            if (! file_exists($credentialsPath)) {
                Log::warning("FcmService: Firebase credentials file not found at: [{$credentialsPath}]");

                return null;
            }

            try {
                $client = new Client();
                $client->setAuthConfig($credentialsPath);
                $client->addScope($this->getFcmScope());

                $tokenArray = $client->fetchAccessTokenWithAssertion();

                return $tokenArray['access_token'] ?? null;
            } catch (Exception $e) {
                Log::error('FcmService: Failed to acquire Google OAuth2 access token: '.$e->getMessage());

                return null;
            }
        });
    }

    /**
     * استخراج معرف المشروع Project ID من جدول الإعدادات أولاً أو من ملف الاعتماد أو .env.
     */
    public function getProjectId(): ?string
    {
        $id = $this->systemSettingService->getString('firebase_project_id');
        if (! empty($id)) {
            return $id;
        }

        $configuredId = config('services.firebase.project_id');
        if (! empty($configuredId)) {
            return (string) $configuredId;
        }

        $credentialsPath = $this->getCredentialsPath();
        if (file_exists($credentialsPath)) {
            $content = json_decode((string) file_get_contents($credentialsPath), true);
            if (is_array($content) && ! empty($content['project_id'])) {
                return (string) $content['project_id'];
            }
        }

        return null;
    }

    /**
     * التحقق من تفعيل خدمة Firebase في النظام عبر جدول الإعدادات أو .env.
     */
    public function isEnabled(): bool
    {
        return $this->systemSettingService->getBool('firebase_enabled', (bool) config('services.firebase.is_enabled', true));
    }

    /**
     * فحص ما إذا كان استثناء العميل ناتجاً عن رمز غير صالح أو منتهي الصلاحية.
     */
    protected function isUnregisteredTokenError(?int $statusCode, string $responseBody): bool
    {
        if ($statusCode === 404) {
            return true;
        }

        return str_contains($responseBody, 'UNREGISTERED')
            || str_contains($responseBody, 'NOT_FOUND')
            || str_contains($responseBody, 'registration-token-not-registered')
            || str_contains($responseBody, 'INVALID_ARGUMENT');
    }
}
