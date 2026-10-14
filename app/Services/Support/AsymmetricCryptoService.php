<?php

namespace App\Services\Support;

use App\Services\SystemLoggerService;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * خدمة التشفير غير المتماثل والتوثيق الرقمي السيادي (Enterprise Asymmetric Cryptography Service).
 *
 * تتولى وفق أعلى المعايير السيبرانية:
 * 1. إدارة وتوليد أزواج المفاتيح غير المتماثلة (Private/Public Keypairs) وحفظها في القرص المشفر.
 * 2. توقيع كشوفات ووثائق النتائج رقمياً بالمفتاح الخاص للخادم (Ed25519 / Asymmetric Digital Signatures).
 * 3. التعمية والتشفير المصمت للتوكنات (Opaque Envelope Encryption) لمنع تسريب أي معلومات أو خصوصية للطالب.
 * 4. التحقق والفك الصارم للرموز بالمفتاح الخاص على الخادم حصراً لمنع التزوير والتلاعب.
 * 5. نشر المفاتيح العامة بصيغة قياسية عبر نقطة وصول آمنة (Public JWKS/Keys Endpoint).
 * 6. تشفير رسائل العميل بالمفتاح العام للخادم وفكها بالمفتاح الخاص دون تخزين أسرار في التطبيق.
 */
class AsymmetricCryptoService
{
    public const KEY_ID = 'netsales-key-2026-v1';

    public const TOKEN_PREFIX = 'NTS_';

    protected string $keysPath;

    protected string $signPrivateKeyFile;

    protected string $signPublicKeyFile;

    protected string $boxPrivateKeyFile;

    protected string $boxPublicKeyFile;

    public function __construct()
    {
        $this->keysPath = storage_path('app/protected/keys');
        $this->signPrivateKeyFile = $this->keysPath.'/netsales_signing_private.key';
        $this->signPublicKeyFile = $this->keysPath.'/netsales_signing_public.key';
        $this->boxPrivateKeyFile = $this->keysPath.'/netsales_box_private.key';
        $this->boxPublicKeyFile = $this->keysPath.'/netsales_box_public.key';

        $this->ensureKeysExist();
    }

    /**
     * التأكد من وجود أزواج المفاتيح غير المتماثلة أو توليدها تلقائياً عند أول تشغيل.
     */
    public function ensureKeysExist(): void
    {
        if (! File::isDirectory($this->keysPath)) {
            File::makeDirectory($this->keysPath, 0700, true);
        }

        // 1. توليد مفاتيح التوقيع الرقمي (Ed25519 Signing Keypair)
        if (! File::exists($this->signPrivateKeyFile) || ! File::exists($this->signPublicKeyFile)) {
            $signKeypair = sodium_crypto_sign_keypair();
            $signSecret = sodium_crypto_sign_secretkey($signKeypair);
            $signPublic = sodium_crypto_sign_publickey($signKeypair);

            File::put($this->signPrivateKeyFile, bin2hex($signSecret));
            File::put($this->signPublicKeyFile, bin2hex($signPublic));
            chmod($this->signPrivateKeyFile, 0600);
            chmod($this->signPublicKeyFile, 0644);
        }

        // 2. توليد مفاتيح التشفير المصمت غير المتماثل (X25519 Encryption Keypair)
        if (! File::exists($this->boxPrivateKeyFile) || ! File::exists($this->boxPublicKeyFile)) {
            $boxKeypair = sodium_crypto_box_keypair();
            $boxSecret = sodium_crypto_box_secretkey($boxKeypair);
            $boxPublic = sodium_crypto_box_publickey($boxKeypair);

            File::put($this->boxPrivateKeyFile, bin2hex($boxSecret));
            File::put($this->boxPublicKeyFile, bin2hex($boxPublic));
            chmod($this->boxPrivateKeyFile, 0600);
            chmod($this->boxPublicKeyFile, 0644);
        }
    }

    /**
     * جلب مفتاح التوقيع الخاص السري (Server Signing Secret Key).
     */
    protected function getSignSecretKey(): string
    {
        return hex2bin(trim(File::get($this->signPrivateKeyFile)));
    }

    /**
     * جلب مفتاح التوقيع العام (Server Signing Public Key).
     */
    public function getSignPublicKey(): string
    {
        return hex2bin(trim(File::get($this->signPublicKeyFile)));
    }

    /**
     * جلب مفتاح التشفير الخاص السري (Server Box Secret Key).
     */
    protected function getBoxSecretKey(): string
    {
        return hex2bin(trim(File::get($this->boxPrivateKeyFile)));
    }

    /**
     * جلب مفتاح التشفير العام (Server Box Public Key).
     */
    public function getBoxPublicKey(): string
    {
        return hex2bin(trim(File::get($this->boxPublicKeyFile)));
    }

    // /**
    //  * توليد رمز QR مشفر وموقع رقمياً للفواتير والتقارير (Encrypted Verifiable Result Token).
    //  *
    //  * لا يحتوي على أي نصوص واضحة أو أسماء أو درجات مكشوفة؛ بل كتلة مشفرة مصمتة 100%.
    //  */
    // public function generateResultQrToken($data): string
    // {
    //     $finalStatus = $data->final_status instanceof \BackedEnum
    //         ? $data->final_status->value
    //         : (string) $data->final_status;

    //     $canonicalData = json_encode([
    //         'uuid' => (string) ($data->result_uuid ?? ''),
    //         'sn' => (string) ($data->student_seat_number ?? ''),
    //         'ay' => (string) $data->academic_year,
    //         'cid' => (int) $data->center_id,
    //         'sc' => (float) $data->total_score,
    //         'mx' => (float) ($data->total_max_score ?? $data->max_score ?? 0),
    //         'pct' => (float) $data->percentage,
    //         'st' => (string) $finalStatus,
    //         'iat' => now('Asia/Aden')->timestamp,
    //         'nonce' => bin2hex(random_bytes(8)),
    //     ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    //     // خطوة 1: توقيع البيانات رقمياً بالمفتاح الخاص (Ed25519 Detached Signature)
    //     $signature = sodium_crypto_sign_detached($canonicalData, $this->getSignSecretKey());

    //     // خطوة 2: حزم البيانات والتوقيع معاً
    //     $envelope = json_encode([
    //         'p' => $canonicalData,
    //         's' => bin2hex($signature),
    //         'kid' => self::KEY_ID,
    //     ], JSON_UNESCAPED_UNICODE);

    //     // خطوة 3: التشفير غير المتماثل التام (Anonymous Public-Key Encryption via crypto_box_seal)
    //     $ciphertext = sodium_crypto_box_seal($envelope, $this->getBoxPublicKey());

    //     // خطوة 4: التشفير في صيغة URL-Safe Base64
    //     return self::TOKEN_PREFIX.$this->base64UrlEncode($ciphertext);
    // }

    // /**
    //  * فك التشفير والتحقق الصارم من صحة توكن الـ QR بالمفتاح الخاص للخادم.
    //  *
    //  * @throws ValidationException
    //  */
    // public function decryptAndVerifyQrToken(string $rawToken): array
    // {
    //     $token = trim($rawToken);

    //     if (! str_starts_with($token, self::TOKEN_PREFIX)) {
    //         SystemLoggerService::warning('QR_VERIFICATION_FAILED_INVALID_FORMAT', ['token_prefix' => substr($token, 0, 10)]);
    //         throw ValidationException::withMessages([
    //             'qr_token' => ['رمز التحقق غير صالح أو غير معتمد من منصة مبيعات الشبكة.'],
    //         ]);
    //     }

    //     $base64Cipher = substr($token, strlen(self::TOKEN_PREFIX));
    //     $ciphertext = $this->base64UrlDecode($base64Cipher);

    //     if ($ciphertext === false || empty($ciphertext)) {
    //         throw ValidationException::withMessages([
    //             'qr_token' => ['بنية تشفير الرمز تالفة أو غير صالحة.'],
    //         ]);
    //     }

    //     // خطوة 1: فك التشفير غير المتماثل بالمفتاح الخاص للخادم
    //     try {
    //         $boxKeypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
    //             $this->getBoxSecretKey(),
    //             $this->getBoxPublicKey()
    //         );

    //         $decryptedJson = sodium_crypto_box_seal_open($ciphertext, $boxKeypair);
    //         if ($decryptedJson === false) {
    //             throw new Exception('Decryption failed.');
    //         }
    //     } catch (Exception $e) {
    //         SystemLoggerService::warning('QR_DECRYPTION_FAILED', ['error' => $e->getMessage()]);
    //         throw ValidationException::withMessages([
    //             'qr_token' => ['فشل فك تشفير الرمز؛ الرمز مزور أو لم يصدر عن خادم مبيعات الشبكة المعتمد.'],
    //         ]);
    //     }

    //     $envelope = json_decode($decryptedJson, true);
    //     if (! is_array($envelope) || empty($envelope['p']) || empty($envelope['s'])) {
    //         throw ValidationException::withMessages([
    //             'qr_token' => ['محتوى رمز التحقق مشوه أو ناقص.'],
    //         ]);
    //     }

    //     $canonicalData = $envelope['p'];
    //     $signature = hex2bin($envelope['s']);

    //     // خطوة 2: التحقق الرياضي الصارم من التوقيع الرقمي بالمفتاح العام للتوقيع
    //     $isValidSignature = sodium_crypto_sign_verify_detached(
    //         $signature,
    //         $canonicalData,
    //         $this->getSignPublicKey()
    //     );

    //     if (! $isValidSignature) {
    //         SystemLoggerService::error('QR_SIGNATURE_TAMPERING_DETECTED', [
    //             'data' => $canonicalData,
    //         ]);

    //         throw ValidationException::withMessages([
    //             'qr_token' => ['تحذير أمني عالي الخطورة: تم التلاعب ببيانات النتيجة والتوقيع الرقمي غير مطابق!'],
    //         ]);
    //     }

    //     $payload = json_decode($canonicalData, true);
    //     if (! is_array($payload) || empty($payload['uuid']) || empty($payload['sn'])) {
    //         throw ValidationException::withMessages([
    //             'qr_token' => ['بيانات النتيجة المستخرجة غير صالحة.'],
    //         ]);
    //     }

    //     return $payload;
    // }

    /**
     * تشفير رسالة أو بيانات حساسة موجهة للخادم بواسطة المفتاح العام (Client -> Server).
     */
    public function encryptForServer(string $plaintext): string
    {
        $ciphertext = sodium_crypto_box_seal($plaintext, $this->getBoxPublicKey());

        return $this->base64UrlEncode($ciphertext);
    }

    /**
     * فك تشفير رسالة موجهة للخادم بواسطة المفتاح الخاص (Server Decrypts Client Data).
     */
    public function decryptFromServer(string $base64Cipher): string
    {
        $cipher = trim($base64Cipher);
        if (str_starts_with($cipher, 'NTS_ENC_')) {
            $cipher = substr($cipher, 8);
        }

        $ciphertext = $this->base64UrlDecode($cipher);
        if ($ciphertext === false) {
            $ciphertext = base64_decode($cipher);
        }

        if ($ciphertext === false || empty($ciphertext)) {
            throw new Exception('صيغة التشفير غير صالحة.');
        }

        $boxKeypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            $this->getBoxSecretKey(),
            $this->getBoxPublicKey()
        );

        $plaintext = sodium_crypto_box_seal_open($ciphertext, $boxKeypair);
        if ($plaintext === false) {
            throw new Exception('فشل فك تشفير البيانات المشفرة بالمفتاح الخاص.');
        }

        return $plaintext;
    }

    /**
     * تشفير بيانات مرسلة للعميل (Server -> Client) بالمعمارية اللامتناظرة الصارمة (Strict Asymmetric Flow).
     * يشترط تزويد المفتاح العام للعميل (X25519) عبر الهيدر [X-Client-Public-Key] ليتمكن العميل وحده من فك التشفير بمفتاحه الخاص.
     *
     * @throws InvalidArgumentException إذا لم يتم تزويد مفتاح العميل العام أو كان غير صالح
     */
    public function encryptForClient(string $plaintext, ?string $clientPublicKey = null): string
    {
        if (empty($clientPublicKey)) {
            throw new InvalidArgumentException('خطأ في التشفير.');
        }

        $clientKeyTrimmed = trim($clientPublicKey);
        $pubKeyBin = ctype_xdigit($clientKeyTrimmed) && strlen($clientKeyTrimmed) === 64
            ? hex2bin($clientKeyTrimmed)
            : base64_decode($clientKeyTrimmed);

        if ($pubKeyBin === false || strlen($pubKeyBin) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new InvalidArgumentException('المفتاح العام للعميل غير صالح.');
        }

        $sealed = sodium_crypto_box_seal($plaintext, $pubKeyBin);

        return 'NTS_ENC_'.$this->base64UrlEncode($sealed);
    }

    /**
     * تفاصيل المفاتيح العامة لنشرها عبر واجهة الـ API العامة.
     */
    public function getPublicKeysMetadata(): array
    {
        return [
            'keys' => [
                [
                    'kid' => self::KEY_ID,
                    'kty' => 'OKP',
                    'crv' => 'Ed25519',
                    'use' => 'sig',
                    'alg' => 'EdDSA',
                    'public_key' => bin2hex($this->getSignPublicKey()),
                ],
                [
                    'kid' => self::KEY_ID.'-enc',
                    'kty' => 'OKP',
                    'crv' => 'X25519',
                    'use' => 'enc',
                    'alg' => 'ECDH-ES',
                    'public_key' => bin2hex($this->getBoxPublicKey()),
                ],
            ],
            'server_time' => now('Asia/Aden')->toDateTimeString(),
        ];
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
