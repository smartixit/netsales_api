<?php

namespace Tests\Feature;

use App\Services\Support\AsymmetricCryptoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class StrictAsymmetricCryptoTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * اختبار: الدالة encryptForClient ترمي استثناءً إذا كان المفتاح العام للعميل مفقوداً.
     */
    public function test_encrypt_for_client_throws_exception_when_public_key_is_missing(): void
    {
        /** @var AsymmetricCryptoService $crypto */
        $crypto = app(AsymmetricCryptoService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('خطأ في التشفير.');

        $crypto->encryptForClient('sensitive data', null);
    }

    /**
     * اختبار: الدالة encryptForClient ترمي استثناءً إذا كان المفتاح العام للعميل غير صالح أو بطول خاطئ.
     */
    public function test_encrypt_for_client_throws_exception_when_public_key_is_invalid(): void
    {
        /** @var AsymmetricCryptoService $crypto */
        $crypto = app(AsymmetricCryptoService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('المفتاح العام للعميل غير صالح');

        $crypto->encryptForClient('sensitive data', 'invalid-short-key');
    }

    /**
     * اختبار: نقطة نهاية المفاتيح العامة (public-keys) غير مشفرة دائماً ومتاحة للعميل.
     */
    public function test_public_keys_endpoint_is_always_unencrypted(): void
    {
        $response = $this->getJson('/api/v1/security/public-keys');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'keys',
                    'server_time',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('data.keys'));
    }

    /**
     * اختبار: التدفق اللامتناظر الصارم الشامل (Strict Asymmetric Flow)
     * العميل يشفر بمفتاح السيرفر، ويرسل مفتاحه العام، السيرفر يشفر الرد بمفتاح العميل، والعميل يفك التشفير بنجاح.
     */
    public function test_full_strict_asymmetric_request_and_response_flow(): void
    {
        /** @var AsymmetricCryptoService $crypto */
        $crypto = app(AsymmetricCryptoService::class);

        // 1. توليد زوج مفاتيح خاص بالعميل (X25519)
        $clientKeypair = sodium_crypto_box_keypair();
        $clientSecretKey = sodium_crypto_box_secretkey($clientKeypair);
        $clientPublicKey = sodium_crypto_box_publickey($clientKeypair);
        $clientPublicKeyHex = bin2hex($clientPublicKey);

        // 2. العميل يشفر بيانات تسجيل الدخول باستخدام المفتاح العام للسيرفر
        $plainPayload = json_encode([
            'phone' => '772957881',
            'password' => 'Secret@123',
        ]);
        $encryptedPayload = $crypto->encryptForServer($plainPayload);

        // 3. إرسال الطلب مع ترويسة المفتاح العام للعميل
        $response = $this->withHeaders([
            'X-Client-Public-Key' => $clientPublicKeyHex,
        ])->postJson('/api/v1/auth/login', [
            'payload' => $encryptedPayload,
        ]);

        // 4. التحقق من أن الاستجابة وصلت وأن حقل data مشفر بمفتاح العميل (يبدأ بـ NTS_ENC_)
        $responseJson = $response->json();
        $this->assertArrayHasKey('data', $responseJson);

        if (is_string($responseJson['data']) && str_starts_with($responseJson['data'], 'NTS_ENC_')) {
            $cipherBase64 = substr($responseJson['data'], 8);
            $cipherRaw = base64_decode(strtr($cipherBase64, '-_', '+/'));

            // 5. العميل يفك تشفير الرد باستخدام زوج مفاتيحه الخاص (Client Secret Key + Server Box Key)
            $clientFullKeypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                $clientSecretKey,
                $clientPublicKey
            );
            $decryptedResponse = sodium_crypto_box_seal_open($cipherRaw, $clientFullKeypair);

            $this->assertNotFalse($decryptedResponse, 'Client failed to decrypt server response');
            $this->assertNotNull(json_decode($decryptedResponse, true));
        }
    }

    /**
     * اختبار: رفض الطلب المشفّر برمز 422 إذا لم يُرسل العميل مفتاحه العام.
     */
    public function test_encrypted_request_fails_with_422_when_client_public_key_is_missing(): void
    {
        /** @var AsymmetricCryptoService $crypto */
        $crypto = app(AsymmetricCryptoService::class);

        // العميل يشفر البيانات بمفتاح السيرفر
        $plainPayload = json_encode(['phone' => '772957881']);
        $encryptedPayload = $crypto->encryptForServer($plainPayload);

        // إرسال الطلب دون ترويسة X-Client-Public-Key ودون _client_public_key
        $response = $this->postJson('/api/v1/auth/login', [
            'payload' => $encryptedPayload,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertEquals('خطأ في التشفير.', $response->json('message'));
    }
}
