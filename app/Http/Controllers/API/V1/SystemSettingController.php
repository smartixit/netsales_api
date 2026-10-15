<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Setting\BatchUpdateSystemSettingRequest;
use App\Http\Requests\API\V1\Setting\StoreSystemSettingRequest;
use App\Http\Requests\API\V1\Setting\UpdateSystemSettingRequest;
use App\Http\Resources\API\V1\Setting\SystemSettingResource;
use App\Services\SystemSettingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة إعدادات النظام وتهيئة البيئة (System Setting Controller).
 *
 * متحكم نحيف صارم (Strict Lean Controller) يلتزم بالمعايير:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة مع ApiResponseTrait.
 * 3. حظر كتابة أي استعلامات مباشرة أو DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'SystemSettings', description: 'إدارة وتخصيص إعدادات النظام والتكوين التشغيلي الموحد')]
class SystemSettingController extends BaseController
{
    public function __construct(
        protected SystemSettingService $settingService
    ) {}

    /**
     * عرض قائمة إعدادات النظام مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/system-settings',
        summary: 'عرض قائمة إعدادات النظام',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'setting_category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'setting_value_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الإعدادات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['setting_category', 'setting_value_type', 'search', 'per_page', 'all']);
            $settings = $this->settingService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة إعدادات النظام بنجاح.', SystemSettingResource::collection($settings));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة إعدادات النظام: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض إعدادات تصنيف محدد.
     */
    #[OA\Get(
        path: '/api/v1/system-settings/category/{category}',
        summary: 'عرض إعدادات تصنيف محدد',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع إعدادات التصنيف بنجاح'),
        ]
    )]
    public function byCategory(string $category): JsonResponse
    {
        try {
            $settings = $this->settingService->getByCategory($category);

            return $this->successResponse("تم استرجاع إعدادات التصنيف [{$category}] بنجاح.", SystemSettingResource::collection($settings));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب إعدادات التصنيف: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض إعداد محدد عبر المفتاح الفريد.
     */
    #[OA\Get(
        path: '/api/v1/system-settings/key/{key}',
        summary: 'عرض إعداد محدد عبر المفتاح',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الإعداد بنجاح'),
        ]
    )]
    public function byKey(string $key): JsonResponse
    {
        try {
            $setting = $this->settingService->getByKey($key);

            return $this->successResponse("تم استرجاع الإعداد [{$key}] بنجاح.", new SystemSettingResource($setting));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الإعداد: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل إعداد محدد عبر المعرف الرقمي.
     */
    #[OA\Get(
        path: '/api/v1/system-settings/{id}',
        summary: 'عرض تفاصيل إعداد محدد',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الإعداد بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $setting = $this->settingService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل الإعداد بنجاح.', new SystemSettingResource($setting));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب تفاصيل الإعداد: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء إعداد نظام جديد.
     */
    #[OA\Post(
        path: '/api/v1/system-settings',
        summary: 'إنشاء إعداد نظام جديد',
        
        tags: ['SystemSettings'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['setting_category', 'setting_key', 'setting_value_type'],
                    properties: [
                    new OA\Property(property: 'setting_category', type: 'string'),
                    new OA\Property(property: 'setting_key', type: 'string'),
                    new OA\Property(property: 'setting_value_type', type: 'string'),
                    new OA\Property(property: 'setting_value', type: 'string'),
                    new OA\Property(property: 'setting_desc', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء إعداد النظام بنجاح'),
        ]
    )]
    public function store(StoreSystemSettingRequest $request): JsonResponse
    {
        try {
            $setting = $this->settingService->create($request);

            return $this->successResponse('تم إنشاء إعداد النظام بنجاح.', new SystemSettingResource($setting), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء إعداد النظام: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات إعداد نظام قائم.
     */
    #[OA\Put(
        path: '/api/v1/system-settings/{id}',
        summary: 'تحديث بيانات إعداد نظام',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [],
                    properties: [
                    new OA\Property(property: 'setting_category', type: 'string'),
                    new OA\Property(property: 'setting_key', type: 'string'),
                    new OA\Property(property: 'setting_value_type', type: 'string'),
                    new OA\Property(property: 'setting_value', type: 'string'),
                    new OA\Property(property: 'setting_desc', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث إعداد النظام بنجاح'),
        ]
    )]
    public function update(UpdateSystemSettingRequest $request, int $id): JsonResponse
    {
        try {
            $setting = $this->settingService->update($request, $id);

            return $this->successResponse('تم تحديث إعداد النظام بنجاح.', new SystemSettingResource($setting));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث إعداد النظام: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث مجمع لقيم إعدادات النظام.
     */
    #[OA\Post(
        path: '/api/v1/system-settings/batch-update',
        summary: 'تحديث مجمع لقيم إعدادات النظام',
        
        tags: ['SystemSettings'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['settings'],
                    properties: [
                    new OA\Property(property: 'settings[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث الإعدادات المحددة بنجاح'),
        ]
    )]
    public function batchUpdate(BatchUpdateSystemSettingRequest $request): JsonResponse
    {
        try {
            $count = $this->settingService->batchUpdate($request);

            return $this->successResponse("تم تحديث [{$count}] من إعدادات النظام بنجاح.", ['updated_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر التحديث المجمع للإعدادات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف إعداد نظام ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/system-settings/{id}',
        summary: 'حذف إعداد نظام ناعماً',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف إعداد النظام بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->settingService->delete($id);

            return $this->successResponse('تم حذف إعداد النظام بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف إعداد النظام: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة الإعدادات المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/system-settings/trashed',
        summary: 'عرض الإعدادات المحذوفة ناعماً',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'setting_category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الإعدادات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['setting_category', 'search', 'per_page', 'all']);
            $settings = $this->settingService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع الإعدادات المحذوفة ناعماً بنجاح.', SystemSettingResource::collection($settings));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الإعدادات المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة إعداد نظام محذوف ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/system-settings/{id}/restore',
        summary: 'استعادة إعداد نظام محذوف ناعماً',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة إعداد النظام بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $setting = $this->settingService->restore($id);

            return $this->successResponse('تم استعادة إعداد النظام بنجاح.', new SystemSettingResource($setting));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة إعداد النظام: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لإعداد نظام.
     */
    #[OA\Delete(
        path: '/api/v1/system-settings/{id}/force-delete',
        summary: 'الحذف النهائي لإعداد نظام',
        
        tags: ['SystemSettings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الإعداد نهائياً بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->settingService->forceDelete($id);

            return $this->successResponse('تم حذف إعداد النظام نهائياً من قاعدة البيانات.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للإعداد: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع للإعدادات ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/system-settings/batch-delete',
        summary: 'الحذف المجمع للإعدادات ناعماً',
        
        tags: ['SystemSettings'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                    new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الإعدادات المحددة بنجاح'),
        ]
    )]
    public function batchDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->settingService->bulkDelete($request);

            return $this->successResponse("تم حذف [{$count}] من إعدادات النظام بنجاح.", ['deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف المجمع للإعدادات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة للإعدادات المحذوفة ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/system-settings/batch-restore',
        summary: 'الاستعادة المجمعة للإعدادات',
        
        tags: ['SystemSettings'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                    new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الإعدادات المحددة بنجاح'),
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->settingService->bulkRestore($request);

            return $this->successResponse("تم استعادة [{$count}] من إعدادات النظام بنجاح.", ['restored_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الاستعادة المجمعة للإعدادات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع للإعدادات.
     */
    #[OA\Post(
        path: '/api/v1/system-settings/batch-force-delete',
        summary: 'الحذف النهائي المجمع للإعدادات',
        
        tags: ['SystemSettings'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                    new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الإعدادات نهائياً بنجاح'),
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->settingService->bulkForceDelete($request);

            return $this->successResponse("تم حذف [{$count}] من إعدادات النظام نهائياً بنجاح.", ['force_deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي المجمع للإعدادات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تفريغ الذاكرة المؤقتة للإعدادات يدوياً.
     */
    #[OA\Post(
        path: '/api/v1/system-settings/clear-cache',
        summary: 'تفريغ كاش إعدادات النظام',
        
        tags: ['SystemSettings'],
        responses: [
            new OA\Response(response: 200, description: 'تم تفريغ كاش إعدادات النظام بنجاح'),
        ]
    )]
    public function clearCache(): JsonResponse
    {
        try {
            $this->settingService->clearCache();

            return $this->successResponse('تم تفريغ الذاكرة المؤقتة لإعدادات النظام بنجاح.');
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تفريغ الذاكرة المؤقتة: '.$e->getMessage(), [], 422);
        }
    }
}
