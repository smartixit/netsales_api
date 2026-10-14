<?php

use App\Services\Support\AsymmetricCryptoService;
use App\Services\SystemSettingService;
use App\Support\AmountFormatter;

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

if (! function_exists('formatAmount')) {
    /**
     * تنسيق المبلغ المالي بفاصلة الآلاف والتحكم بالمنازل العشرية مع تجريد الأصفار الزائدة.
     */
    function formatAmount(
        float|int|string|null $amount,
        int $maxDecimals = 2,
        bool $trimZeroDecimals = false,
        string $decPoint = '.',
        string $thousandsSep = ','
    ): string {
        return AmountFormatter::format($amount, $maxDecimals, $trimZeroDecimals, $decPoint, $thousandsSep);
    }
}

if (! function_exists('formatWithCurrency')) {
    /**
     * تنسيق المبلغ المالي وإلحاق رمز أو اسم العملة (مثل 1,000YER).
     */
    function formatWithCurrency(
        float|int|string|null $amount,
        string $currency = 'YER',
        int $maxDecimals = 2,
        bool $space = false,
        bool $trimZeroDecimals = false
    ): string {
        return AmountFormatter::formatWithCurrency($amount, $currency, $maxDecimals, $space, $trimZeroDecimals);
    }
}
