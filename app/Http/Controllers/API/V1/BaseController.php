<?php

namespace App\Http\Controllers\API\V1;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use OpenApi\Attributes as OA;

#[OA\OpenApi(
    security: [
        [
            'sessionAuth' => [],
            'deviceAuth' => [],
            'clientPublicKey' => [],
            'bypassEncryption' => [],
        ],
        [
            'sessionAuth' => [],
            'clientPublicKey' => [],
            'bypassEncryption' => [],
        ],
        [
            'clientPublicKey' => [],
            'bypassEncryption' => [],
        ],
        [
            'bypassEncryption' => [],
        ],
    ]
)]
#[OA\Info(
    version: '1.0.0',
    description: 'توثيق واجهة البرمجة (API) لنظام مبيعات الشبكة NetSales',
    title: 'نظام مبيعات الشبكة | NetSales API',
)]
#[OA\Server(
    url: '/',
    description: 'خادم واجهة البرمجة الأساسي (Primary API Server)'
)]
#[OA\SecurityScheme(
    securityScheme: 'sessionAuth',
    type: 'apiKey',
    name: 'X-Session-Id',
    in: 'header',
    description: 'معرف الجلسة النشطة (Session ID) المسجلة في جدول user_sessions'
)]
#[OA\SecurityScheme(
    securityScheme: 'deviceAuth',
    type: 'apiKey',
    name: 'X-Device-Uuid',
    in: 'header',
    description: 'معرف الجهاز الفريد (Device UUID) الخاص بجهاز المستخدم'
)]
#[OA\SecurityScheme(
    securityScheme: 'clientPublicKey',
    type: 'apiKey',
    name: 'X-Client-Public-Key',
    in: 'header',
    description: 'المفتاح العام للعميل (Client Public Key بصيغة Hex أو Base64 X25519) لتشفير الرد الصادر'
)]
#[OA\SecurityScheme(
    securityScheme: 'bypassEncryption',
    type: 'apiKey',
    name: 'X-Bypass-Encryption',
    in: 'header',
    description: 'رأس مخصص (Header) لتجاوز التشفير أثناء الاختبار والبرمجة'
)]
class BaseController
{
    use ApiResponseTrait;

    public const SYSTEM_USER_ID = 0;

    private ?User $resolvedCurrentUser = null;

    private bool $currentUserResolved = false;

    /**
     * استخراج كائن المستخدم الحالي من الجلسة أو كائن النظام الآلي مع التخزين المؤقت للطلب.
     */
    protected function currentUser(bool $fallbackToSystem = false): ?User
    {
        if ($this->currentUserResolved && $this->resolvedCurrentUser !== null) {
            return $this->resolvedCurrentUser;
        }

        /** @var User|null $user */
        $user = auth()->user();

        if ($user instanceof User) {
            $this->resolvedCurrentUser = $user;
            $this->currentUserResolved = true;

            return $user;
        }

        if ($fallbackToSystem) {
            $systemUser = app(UserRepositoryInterface::class)->findById(self::SYSTEM_USER_ID);
            if ($systemUser instanceof User) {
                $this->resolvedCurrentUser = $systemUser;
                $this->currentUserResolved = true;

                return $systemUser;
            }
        }

        return null;
    }

    /**
     * استخراج كائن المستخدم الحالي مع اشتراط وجوده أمنياً (رمي استثناء 401 إن لم يكن موثقاً).
     *
     * @throws AuthenticationException
     */
    protected function requireCurrentUser(bool $fallbackToSystem = false): User
    {
        $user = $this->currentUser($fallbackToSystem);

        if (! $user instanceof User) {
            throw new AuthenticationException('المستخدم غير مصادق عليه أو انتهت صلاحية الجلسة.');
        }

        return $user;
    }

    /**
     * استخراج معرّف المستخدم الحالي كرقم صحيح (int) إلزامي دون إرجاع null نهائياً.
     * في الحالات التي لا يوجد بها جلسة وكان الخيار $fallbackToSystem مفعلاً، يُسند معرف النظام الآلي 0.
     *
     * @throws AuthenticationException
     */
    protected function currentUserId(bool $fallbackToSystem = true): int
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return (int) $user->user_id;
        }

        if ($fallbackToSystem) {
            return self::SYSTEM_USER_ID;
        }

        throw new AuthenticationException('المستخدم غير مصادق عليه أو انتهت صلاحية الجلسة.');
    }

    /**
     * التحقق مما إذا كان المستخدم الحالي مديراً عاماً للنظام (Super Admin).
     */
    protected function isSuperAdmin(): bool
    {
        return (bool) $this->currentUser()?->isSuperAdmin();
    }
}
