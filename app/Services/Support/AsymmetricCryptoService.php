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
