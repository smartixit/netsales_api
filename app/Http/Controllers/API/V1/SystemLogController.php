<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\API\V1\System\SystemLogResource;
use App\Services\SystemLogService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم استعلامات وتدقيق سجلات النظام (System Log Controller).
 *
 * متحكم نحيف صارم (Strict Lean Controller) يلتزم بالمعايير:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة مع ApiResponseTrait.
 * 3. حظر كتابة أي استعلامات مباشرة أو DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'SystemLogs', description: 'استعلامات سجلات تدقيق النظام والتتبع الأمني ومسار العمليات')]
class SystemLogController extends BaseController
{
    public function __construct(
        protected SystemLogService $logService
    ) {}

    /**
     * عرض قائمة سجلات تدقيق النظام مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/system-logs',
        summary: 'عرض قائمة سجلات تدقيق النظام',
        
        tags: ['SystemLogs'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'module', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'action', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'entity_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'entity_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة سجلات التدقيق بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'user_id',
                'module',
                'action',
                'entity_type',
                'entity_id',
                'ip_address',
                'from_date',
                'to_date',
                'search',
                'per_page',
                'all',
            ]);

            $logs = $this->logService->getFiltered($filters);

            return $this->successResponse('تم استرجاع سجلات تدقيق النظام بنجاح.', SystemLogResource::collection($logs));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب سجلات تدقيق النظام: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إحصائيات النشاط العام ومؤشرات سجلات التدقيق.
     */
    #[OA\Get(
        path: '/api/v1/system-logs/stats',
        summary: 'إحصائيات ومؤشرات سجلات التدقيق',
        
        tags: ['SystemLogs'],
        parameters: [
            new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع إحصائيات السجلات بنجاح'),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['from_date', 'to_date']);
            $stats = $this->logService->getStats($filters);

            return $this->successResponse('تم استرجاع إحصائيات سجلات التدقيق بنجاح.', $stats);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب إحصائيات السجلات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب قائمة الوحدات البرمجية المسجلة في السجلات.
     */
    #[OA\Get(
        path: '/api/v1/system-logs/modules',
        summary: 'قائمة الوحدات البرمجية المسجلة',
        
        tags: ['SystemLogs'],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الوحدات بنجاح'),
        ]
    )]
    public function modules(): JsonResponse
    {
        try {
            $modules = $this->logService->getModules();

            return $this->successResponse('تم استرجاع قائمة الوحدات بنجاح.', ['modules' => $modules]);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الوحدات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب قائمة الإجراءات المسجلة في السجلات.
     */
    #[OA\Get(
        path: '/api/v1/system-logs/actions',
        summary: 'قائمة الإجراءات المسجلة',
        
        tags: ['SystemLogs'],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الإجراءات بنجاح'),
        ]
    )]
    public function actions(): JsonResponse
    {
        try {
            $actions = $this->logService->getActions();

            return $this->successResponse('تم استرجاع قائمة الإجراءات بنجاح.', ['actions' => $actions]);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الإجراءات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض سجلات كيان محدد.
     */
    #[OA\Get(
        path: '/api/v1/system-logs/entity/{entityType}/{entityId}',
        summary: 'عرض سجلات كيان محدد',
        
        tags: ['SystemLogs'],
        parameters: [
            new OA\Parameter(name: 'entityType', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'entityId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع سجلات الكيان بنجاح'),
        ]
    )]
    public function byEntity(string $entityType, int $entityId): JsonResponse
    {
        try {
            $logs = $this->logService->getByEntity($entityType, $entityId);

            return $this->successResponse("تم استرجاع سجلات الكيان [{$entityType}:{$entityId}] بنجاح.", SystemLogResource::collection($logs));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب سجلات الكيان: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض سجلات تدقيق مستخدم محدد.
     */
    #[OA\Get(
        path: '/api/v1/system-logs/user/{userId}',
        summary: 'عرض سجلات مستخدم محدد',
        
        tags: ['SystemLogs'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع سجلات المستخدم بنجاح'),
        ]
    )]
    public function byUser(int $userId, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['module', 'action', 'from_date', 'to_date', 'search', 'per_page', 'all']);
            $logs = $this->logService->getByUser($userId, $filters);

            return $this->successResponse("تم استرجاع سجلات المستخدم رقم [{$userId}] بنجاح.", SystemLogResource::collection($logs));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب سجلات المستخدم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل سجل تدقيق محدد.
     */
    #[OA\Get(
        path: '/api/v1/system-logs/{id}',
        summary: 'عرض تفاصيل سجل تدقيق محدد',
        
        tags: ['SystemLogs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل السجل بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $log = $this->logService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل السجل بنجاح.', new SystemLogResource($log));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب تفاصيل السجل: '.$e->getMessage(), [], 422);
        }
    }
}
