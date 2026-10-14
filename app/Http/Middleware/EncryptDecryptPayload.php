<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class EncryptDecryptPayload
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow Bypass for Swagger UI, Debugging via Header/Query/Body, or Public Key / Healthcheck Endpoints
        $bypassHeader = $request->header('X-Bypass-Encryption')
            ?? $request->header('bypass-encryption')
            ?? $request->input('bypass_encryption')
            ?? $request->query('bypass_encryption');

        $bypassEncryption = in_array(strtolower((string) $bypassHeader), ['true', '1', 'yes', 'on'])
            || $request->is('api/v1/security/public-keys')
            || $request->is('up');

        // 1. INBOUND DECRYPTION
        if (! $bypassEncryption && in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $payload = $request->input('payload');

            // If the incoming request has a Base64 encrypted `payload` key
            if ($payload && is_string($payload)) {
                $decrypted = decryptData($payload);
                if ($decrypted) {
                    $decoded = json_decode($decrypted, true);
                    if (is_array($decoded)) {
                        if (! empty($decoded['_client_public_key'])) {
                            $request->headers->set('X-Client-Public-Key', (string) $decoded['_client_public_key']);
                            unset($decoded['_client_public_key']);
                        }
                        // Dynamically merge the decrypted payload variables into the Request object's lifecycle
                        $request->replace($decoded);
                    }
                }

                // إلزام العميل بتمرير مفتاحه العام في كل طلب مشفر يتطلب استجابة مشفرة (Strict Asymmetric Flow)
                if (! $request->header('X-Client-Public-Key')) {
                    throw new InvalidArgumentException('خطأ في التشفير.');
                }
            }
        }

        // 2. PROCESS REQUEST
        $response = $next($request);

        // 3. OUTBOUND ENCRYPTION
        if (! $bypassEncryption && $response instanceof JsonResponse) {
            $originalData = $response->getData(true);

            // Strictly target our standard ApiResponseTrait format: {success: bool, message: string, data: array|object, errors: array}
            if (isset($originalData['success'], $originalData['data']) && ! empty($originalData['data'])) {
                $jsonString = json_encode($originalData['data']);

                if ($jsonString) {
                    $originalData['data'] = encryptData($jsonString, $request->header('X-Client-Public-Key'));
                    $response->setData($originalData);
                }
            }
        }

        return $response;
    }
}
