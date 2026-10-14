<?php

use App\Services\Support\AsymmetricCryptoService;
use App\Services\SystemSettingService;

if (! function_exists('encryptData')) {
    /**
     * تشفير البيانات المتبادلة بين الخادم والعميل باستخدام معمارية التشفير اللامتناظرة.
     * يدعم التشفير بمفتاح العميل العام (X-Client-Public-Key) أو التشفير الآمن للخادم.
     */
    function encryptData(string $data, ?string $clientPublicKey = null): string
    {
        /** @var AsymmetricCryptoService $crypto */
        $crypto = app(AsymmetricCryptoService::class);
        $clientKey = $clientPublicKey ?? (request()?->header('X-Client-Public-Key'));

        return $crypto->encryptForClient($data, $clientKey);
    }
}

if (! function_exists('decryptData')) {
    /**
     * فك تشفير حمولة الطلبات القادمة من العميل باستخدام المفتاح الخاص للخادم حصراً.
     *
     * @return string|false
     */
    function decryptData(string $data)
    {
        /** @var AsymmetricCryptoService $crypto */
        $crypto = app(AsymmetricCryptoService::class);

        try {
            return $crypto->decryptFromServer($data);
        } catch (Throwable) {
            return false;
        }
    }
}

if (! function_exists('systemSetting')) {
    /**
     * جلب قيمة إعداد من محرك الإعدادات المؤسسي مع التخزين المؤقت اللحظي.
     */
    function systemSetting(string $key, mixed $default = null, ?int $centerId = null): mixed
    {
        return app(SystemSettingService::class)->get($key, $default, $centerId);
    }
}
