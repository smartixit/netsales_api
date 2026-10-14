<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Traits\ApiResponseTrait;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'توثيق واجهة البرمجة (API) لنظام مبيعات الشبكة NetSales',
    title: 'مبيعات الشبكة | NetSales API',
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
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
    securityScheme: 'bypassEncryption',
    type: 'apiKey',
    name: 'X-Bypass-Encryption',
    in: 'header',
    description: 'رأس مخصص (Header) لتجاوز التشفير أثناء الاختبار والبرمجة'
)]
class BaseController
{
    use ApiResponseTrait;

    protected function currentUser(): ?User
    {
        return auth()->user();
    }
}
