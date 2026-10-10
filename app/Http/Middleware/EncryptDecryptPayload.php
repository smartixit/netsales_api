<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        // Allow Bypass for Swagger UI or Debugging via Header
        $bypassHeader = $request->header('X-Bypass-Encryption');
        $bypassEncryption = in_array(strtolower((string) $bypassHeader), ['true', '1', 'yes', 'on']);

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
