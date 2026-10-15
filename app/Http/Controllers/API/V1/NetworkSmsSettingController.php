<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Network\TestNetworkSmsRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkSmsSettingRequest;
use App\Services\NetworkSmsSettingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة إعدادات وبوابات الرسائل للشبكات (Strict Lean Controller).
 */
#[OA\Tag(name: 'Network SMS Settings', description: 'إدارة إعدادات وبوابات الرسائل النصية المخصصة لكل شبكة')]
class NetworkSmsSettingController extends BaseController
{
    public function __construct(
        protected NetworkSmsSettingService $smsSettingService
    ) {}

    /**
     * حالة خدمة الرسائل: المفتاح العام وملخص كل شبكة
     */
    public function index(): JsonResponse
    {
        try {
            $overview = $this->smsSettingService->getOverview();

            return $this->successResponse('تم جلب إعدادات الرسائل النصية بنجاح.', $overview);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تبديل المفتاح العام للرسائل (سيّد مطلق يطغى على الشبكات)
     */
    public function updateGlobal(Request $request): JsonResponse
    {
        try {
            $enabled = $request->boolean('enabled');
            $this->smsSettingService->updateGlobal($enabled);

            return $this->successResponse(
                $enabled
                    ? 'تم تفعيل خدمة الرسائل النصية لجميع الشبكات.'
                    : 'تم إيقاف خدمة الرسائل النصية بالكامل.',
                ['enabled' => $enabled]
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب تفاصيل إعدادات الرسائل النصية لشبكة محددة (البوابة، الأنواع، والحالة)
     */
    public function show(int $id): JsonResponse
    {
        try {
            $details = $this->smsSettingService->getByNetworkId($id);

            return $this->successResponse('تم جلب إعدادات رسائل الشبكة بنجاح.', $details);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث كامل لإعدادات رسائل الشبكة وبوابتها الخاصة أو العامة
     */
    public function update(UpdateNetworkSmsSettingRequest $request, int $id): JsonResponse
    {
        try {
            $updated = $this->smsSettingService->updateForNetwork($id, $request);

            return $this->successResponse('تم حفظ إعدادات الرسائل النصية للشبكة بنجاح.', $updated);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إرسال رسالة نصية تجريبية للتحقق من سلامة ربط بوابة الشبكة
     */
    public function test(TestNetworkSmsRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->smsSettingService->testSmsForNetwork($id, $request);

            return $this->successResponse('تم إرسال الرسالة النصية التجريبية بنجاح.', $result);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 400);
        }
    }
}
