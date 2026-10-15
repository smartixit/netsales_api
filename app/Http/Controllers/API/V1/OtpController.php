<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\System\GenerateOtpRequest;
use App\Http\Requests\API\V1\System\VerifyOtpRequest;
use App\Services\OtpService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم خدمات التحقق بالرمز لمرة واحدة (Centralized OTP Controller).
 *
 * يلتزم بالنمط الموحد (Lean Controller):
 * - يستقبل كائنات FormRequest المعتمدة ويمررها مباشرة للخدمة دون تفكيك أو استخدام validated داخل الكونترولر.
 */
#[OA\Tag(name: 'OTP', description: 'خدمات التحقق الآمن عبر الرمز المؤقت لمرة واحدة (OTP)')]
class OtpController extends BaseController
{
    public function __construct(
        protected OtpService $otpService,
    ) {}

    /**
     * توليد وإرسال رمز تحقق مؤقت جديد إلى هاتف المستخدم.
     */
    #[OA\Post(
        path: '/api/v1/otp/generate',
        summary: 'توليد وإرسال رمز تحقق جديد (OTP)',
        description: 'يولد رمز تحقق مؤقت معتمد ويرسله عبر رسالة نصية SMS إلى رقم الهاتف المسجل مع مراعاة فترات التهدئة.',
        tags: ['OTP'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_phone_number', 'otp_type'],
                    properties: [
                    new OA\Property(property: 'user_phone_number', type: 'string'),
                    new OA\Property(property: 'otp_type', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم إرسال رمز التحقق بنجاح'),
            new OA\Response(response: 422, description: 'خطأ في صحة البيانات أو فترة التهدئة ما زالت سارية'),
            new OA\Response(response: 500, description: 'خطأ داخلي في الخادم'),
        ]
    )]
    public function generate(GenerateOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->otpService->generateOtp($request);

            $responseData = [
                'user_phone_number' => (string) $request->user_phone_number,
                'expires_at' => $result['expires_at']->toDateTimeString(),
            ];

            if (app()->environment('local', 'testing')) {
                $responseData['debug_otp'] = $result['code'];
            }

            return $this->successResponse(
                'تم إرسال رمز التحقق بنجاح إلى هاتفك عبر رسالة نصية.',
                $responseData
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('عذراً، تعذر توليد رمز التحقق: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * التحقق من صحة ومطابقة رمز التحقق المدخل.
     */
    #[OA\Post(
        path: '/api/v1/otp/verify',
        summary: 'التحقق من صحة رمز OTP',
        description: 'يتحقق من مطابقة الرمز المدخل مع الرمز الصادر والمخزن في النظام وعدم انتهاء صلاحيته أو تجاوز المحاولات.',
        tags: ['OTP'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_phone_number', 'otp'],
                    properties: [
                    new OA\Property(property: 'user_phone_number', type: 'string'),
                    new OA\Property(property: 'otp', type: 'string'),
                    new OA\Property(property: 'type', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تأكيد صحة الرمز بنجاح'),
            new OA\Response(response: 422, description: 'رمز التحقق غير صحيح أو منتهي الصلاحية'),
            new OA\Response(response: 500, description: 'خطأ داخلي في الخادم'),
        ]
    )]
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $this->otpService->verifyOtpRequest($request);

            return $this->successResponse('تم التحقق من صحة الرمز بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل التحقق من رمز OTP: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إعادة إرسال رمز تحقق مؤقت جديد بعد انتهاء فترة التهدئة.
     */
    #[OA\Post(
        path: '/api/v1/otp/resend',
        summary: 'إعادة إرسال رمز التحقق (Resend OTP)',
        description: 'إعادة توليد وإرسال رمز تحقق جديد بعد فحص انقضاء فترة التهدئة المحددة.',
        tags: ['OTP'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_phone_number', 'otp_type'],
                    properties: [
                    new OA\Property(property: 'user_phone_number', type: 'string'),
                    new OA\Property(property: 'otp_type', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تمت إعادة إرسال رمز التحقق بنجاح'),
            new OA\Response(response: 422, description: 'يرجى الانتظار لانقضاء فترة التهدئة'),
            new OA\Response(response: 500, description: 'خطأ داخلي في الخادم'),
        ]
    )]
    public function resend(GenerateOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->otpService->resendOtp($request);

            $responseData = [
                'user_phone_number' => (string) $request->user_phone_number,
                'expires_at' => $result['expires_at']->toDateTimeString(),
            ];

            if (app()->environment('local', 'testing')) {
                $responseData['debug_otp'] = $result['code'];
            }

            return $this->successResponse(
                'تمت إعادة إرسال رمز التحقق بنجاح إلى هاتفك.',
                $responseData
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('عذراً، تعذر إعادة إرسال رمز التحقق: '.$e->getMessage(), [], 422);
        }
    }
}
