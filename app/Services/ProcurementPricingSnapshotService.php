<?php

namespace App\Services;

use App\Support\DecimalAmount;
use Illuminate\Validation\ValidationException;

/**
 * توقيع والتحقق من لقطة تعرفة باقة نقطة البيع.
 *
 * تسمح اللقطة للتطبيق بعرض إجمالي فوري محلياً من أسعار الوحدة التي استلمها،
 * ثم تضمن أن الأسعار لم تتغير قبل إنشاء الطلب. لا تحتوي اللقطة على كميات أو
 * إجماليات يرسلها العميل؛ Laravel يبقى مسؤولاً عن احتساب المبالغ النهائية.
 */
class ProcurementPricingSnapshotService
{
    private const VERSION = 1;

    public const MAX_AGE_SECONDS = 1800;

    public function issue(
        int $networkId,
        int $posId,
        int $packageId,
        int $baseUnitPriceMinor,
        int $cashDiscountBasisPoints,
        int $creditDiscountBasisPoints,
        ?int $issuedAtTimestamp = null,
    ): string {
        $payload = [
            'v' => self::VERSION,
            'nid' => $networkId,
            'pos' => $posId,
            'pid' => $packageId,
            'bp' => $baseUnitPriceMinor,
            'cash' => $cashDiscountBasisPoints,
            'credit' => $creditDiscountBasisPoints,
            'ts' => $issuedAtTimestamp ?? now('Asia/Aden')->timestamp,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $json, (string) config('app.key'));
        $packet = json_encode(
            ['d' => $payload, 's' => $signature],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return rtrim(strtr(base64_encode($packet), '+/', '-_'), '=');
    }

    /**
     * يرفض اللقطة إذا انتهت أو لم تطابق السياق أو التعرفة الحالية.
     */
    public function assertCurrent(
        string $token,
        int $networkId,
        int $posId,
        int $packageId,
        int $baseUnitPriceMinor,
        int $cashDiscountBasisPoints,
        int $creditDiscountBasisPoints,
    ): void {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            $this->reject();
        }

        try {
            $packet = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->reject();
        }

        if (
            ! is_array($packet)
            || ! is_array($packet['d'] ?? null)
            || ! is_string($packet['s'] ?? null)
        ) {
            $this->reject();
        }

        $payload = $packet['d'];
        $expectedSignature = hash_hmac(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            (string) config('app.key')
        );

        if (! hash_equals($expectedSignature, $packet['s'])) {
            $this->reject();
        }

        $timestamp = filter_var($payload['ts'] ?? null, FILTER_VALIDATE_INT);
        $now = now('Asia/Aden')->timestamp;

        if (
            (int) ($payload['v'] ?? 0) !== self::VERSION
            || (int) ($payload['nid'] ?? 0) !== $networkId
            || (int) ($payload['pos'] ?? 0) !== $posId
            || (int) ($payload['pid'] ?? 0) !== $packageId
            || (int) ($payload['bp'] ?? -1) !== $baseUnitPriceMinor
            || (int) ($payload['cash'] ?? -1) !== $cashDiscountBasisPoints
            || (int) ($payload['credit'] ?? -1) !== $creditDiscountBasisPoints
            || $timestamp === false
            || $timestamp > $now + 60
            || ($now - $timestamp) > self::MAX_AGE_SECONDS
        ) {
            $this->reject();
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'items' => [
                'تغيرت تعرفة إحدى الباقات أو انتهت صلاحية بيانات السعر. حدّث كتالوج الباقات ثم أعد الإرسال.',
            ],
        ]);
    }
}
