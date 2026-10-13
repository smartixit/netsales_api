<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\User\StoreUserRequest;
use App\Http\Requests\API\V1\User\SyncPermissionsRequest;
use App\Http\Requests\API\V1\User\UpdateProfilePictureRequest;
use App\Http\Requests\API\V1\User\UpdateUserRequest;
use App\Http\Resources\API\V1\User\UserResource;
use App\Services\UserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة حسابات المستخدمين والعمليات الإدارية (Enterprise User Controller).
 *
 * يوفر إدارة مركزية لحسابات المستخدمين، العمليات الفردية والمجمعة (Batch Operations)،
 * الحذف المؤقت والاستعادة، ومزامنة الصلاحيات المباشرة وفق المعايير المؤسسية.
 */
#[OA\Tag(name: 'Users', description: 'إدارة حسابات المستخدمين، الملفات الشخصية، والعمليات الإدارية')]
class UserController extends BaseController
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /**
     * جلب قائمة المستخدمين مع الفلترة والبحث والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/users',
        summary: 'عرض قائمة المستخدمين',
        description: 'استرجاع قائمة المستخدمين مع دعم الفلترة حسب الحالة، الدور، البحث النصي، والترقيم بالمؤشر (Cursor).',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'بحث بالاسم، رقم الهاتف أو البريد', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role_id', in: 'query', required: false, description: 'تصفية حسب الدور', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'تصفية حسب الحالة', schema: new OA\Schema(type: 'string', enum: ['ACTIVE', 'INACTIVE', 'PENDING', 'BANNED'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'عدد السجلات بالصفحة', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة المستخدمين بنجاح'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'role_id', 'status', 'per_page', 'all']);
        $users = $this->userService->getFiltered($filters);

        return $this->successResponse('تم استرجاع قائمة المستخدمين بنجاح.', UserResource::collection($users));
    }

    /**
     * عرض تفاصيل مستخدم محدد.
     */
    #[OA\Get(
        path: '/api/v1/users/{id}',
        summary: 'عرض تفاصيل مستخدم',
        description: 'جلب بيانات مستخدم محدد مع الدور والأجهزة والصلاحيات المرتبطة به.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع بيانات المستخدم بنجاح'),
            new OA\Response(response: 404, description: 'المستخدم غير موجود'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->findById($id);
        if (! $user) {
            return $this->errorResponse('المستخدم غير موجود في النظام.', [], 404);
        }

        return $this->successResponse('تم جلب بيانات المستخدم بنجاح.', new UserResource($user));
    }

    /**
     * إنشاء حساب مستخدم جديد.
     */
    #[OA\Post(
        path: '/api/v1/users',
        summary: 'إنشاء مستخدم جديد',
        description: 'إضافة حساب مستخدم جديد في النظام وربطه بالدور المحدد وتشفير كلمة المرور ورفع صورته إن وجدت.',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_full_name', 'user_phone_number', 'user_password', 'role_id'],
                    properties: [
                        new OA\Property(property: 'role_id', type: 'integer', example: 2),
                        new OA\Property(property: 'user_full_name', type: 'string', example: 'محمد أحمد'),
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '771234567'),
                        new OA\Property(property: 'user_password', type: 'string', format: 'password', example: 'Secret@123'),
                        new OA\Property(property: 'user_email', type: 'string', format: 'email', example: 'user@example.com'),
                        new OA\Property(property: 'user_profile_logo', type: 'string', format: 'binary'),
                        new OA\Property(property: 'user_2fa_enabled', type: 'boolean', example: false),
                        new OA\Property(property: 'status', type: 'string', enum: ['ACTIVE', 'INACTIVE', 'PENDING'], example: 'ACTIVE'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء المستخدم بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->create($request);

            return $this->successResponse('تم إنشاء حساب المستخدم بنجاح.', new UserResource($user), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل إنشاء المستخدم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات مستخدم قائم.
     */
    #[OA\Put(
        path: '/api/v1/users/{id}',
        summary: 'تحديث بيانات المستخدم',
        description: 'تعديل البيانات الأساسية أو الدور أو كلمة المرور للمستخدم المحدد.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث بيانات المستخدم بنجاح'),
            new OA\Response(response: 404, description: 'المستخدم غير موجود'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->update($id, $request);

            return $this->successResponse('تم تحديث بيانات المستخدم بنجاح.', new UserResource($user));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث بيانات المستخدم: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المؤقت لحساب المستخدم.
     */
    #[OA\Delete(
        path: '/api/v1/users/{id}',
        summary: 'حذف مستخدم (Soft Delete)',
        description: 'نقل المستخدم إلى سلة المحذوفات وإبطال كافة جلساته المفتوحة دون حذفه نهائياً.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المستخدم بنجاح'),
            new OA\Response(response: 404, description: 'المستخدم غير موجود'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->userService->delete($id);
        if (! $deleted) {
            return $this->errorResponse('المستخدم غير موجود أو تعذر حذفه.', [], 404);
        }

        return $this->successResponse('تم حذف حساب المستخدم بنجاح.');
    }

    /**
     * عرض المستخدمين المحذوفين مؤقتاً.
     */
    #[OA\Get(
        path: '/api/v1/users/trashed',
        summary: 'سلة محذوفات المستخدمين',
        description: 'استرجاع قائمة المستخدمين المحذوفين مؤقتاً في النظام.',
        tags: ['Users'],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع المحذوفات بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 15));
        $trashed = $this->userService->getTrashed($perPage);

        return $this->successResponse('تم استرجاع المحذوفات بنجاح.', UserResource::collection($trashed));
    }

    /**
     * استعادة مستخدم محذوف مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/users/{id}/restore',
        summary: 'استعادة مستخدم محذوف',
        description: 'إعادة تفعيل واسترجاع حساب المستخدم من سلة المحذوفات.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تمت استعادة المستخدم بنجاح'),
            new OA\Response(response: 404, description: 'المستخدم غير موجود في المحذوفات'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        $restored = $this->userService->restore($id);
        if (! $restored) {
            return $this->errorResponse('تعذر استعادة المستخدم، قد لا يكون في سلة المحذوفات.', [], 404);
        }

        return $this->successResponse('تمت استعادة حساب المستخدم بنجاح.');
    }

    /**
     * الحذف النهائي للمستخدم من قاعدة البيانات.
     */
    #[OA\Delete(
        path: '/api/v1/users/{id}/force',
        summary: 'حذف نهائي لمستخدم (Force Delete)',
        description: 'حذف حساب المستخدم وصورته نهائياً وبلا رجعة من قاعدة البيانات.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المستخدم نهائياً'),
            new OA\Response(response: 404, description: 'المستخدم غير موجود'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        $deleted = $this->userService->forceDelete($id);
        if (! $deleted) {
            return $this->errorResponse('تعذر الحذف النهائي، المستخدم غير موجود.', [], 404);
        }

        return $this->successResponse('تم حذف حساب المستخدم نهائياً من النظام.');
    }

    /**
     * حذف مجمع لعدة مستخدمين دفعة واحدة.
     */
    #[OA\Post(
        path: '/api/v1/users/batch-delete',
        summary: 'حذف مجمع للمستخدمين (Batch Delete)',
        description: 'حذف مؤقت لمجموعة من المستخدمين عبر إرسال مصفوفة من المعرفات (ids).',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف المجمع بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function bulkDelete(BatchIdsRequest $request): JsonResponse
    {
        $count = $this->userService->bulkDelete($request->getIds());

        return $this->successResponse("تم حذف {$count} من المستخدمين بنجاح.");
    }

    /**
     * استعادة مجمعة لعدة مستخدمين من سلة المحذوفات.
     */
    #[OA\Post(
        path: '/api/v1/users/batch-restore',
        summary: 'استعادة مجمعة للمستخدمين (Batch Restore)',
        description: 'استعادة مجموعة من المستخدمين المحذوفين مؤقتاً دفعة واحدة.',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تمت الاستعادة بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function bulkRestore(BatchIdsRequest $request): JsonResponse
    {
        $count = $this->userService->bulkRestore($request->getIds());

        return $this->successResponse("تمت استعادة {$count} من المستخدمين بنجاح.");
    }

    /**
     * حذف نهائي مجمع لعدة مستخدمين.
     */
    #[OA\Post(
        path: '/api/v1/users/batch-force-delete',
        summary: 'حذف نهائي مجمع للمستخدمين (Batch Force Delete)',
        description: 'الحذف النهائي الدائم لمجموعة مستخدمين دفعة واحدة.',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function bulkForceDelete(BatchIdsRequest $request): JsonResponse
    {
        $count = $this->userService->bulkForceDelete($request->getIds());

        return $this->successResponse("تم الحذف النهائي لعدد {$count} من المستخدمين بنجاح.");
    }

    /**
     * مزامنة الصلاحيات المباشرة للمستخدم.
     */
    #[OA\Post(
        path: '/api/v1/users/{id}/permissions',
        summary: 'مزامنة الصلاحيات المباشرة للمستخدم',
        description: 'تخصيص صلاحيات استثنائية مباشرة لحساب المستخدم المحدد.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['permissions'],
                properties: [
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 5]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تمت مزامنة الصلاحيات بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function syncPermissions(SyncPermissionsRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->syncPermissions($id, $request->getPermissionIds());

            return $this->successResponse('تمت مزامنة صلاحيات المستخدم بنجاح.', new UserResource($user));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشلت مزامنة الصلاحيات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث الصورة الشخصية للمستخدم.
     */
    #[OA\Post(
        path: '/api/v1/users/{id}/profile-picture',
        summary: 'تحديث الصورة الشخصية للمستخدم',
        description: 'رفع واستبدال الصورة الشخصية للمستخدم المحدد.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['profile_picture'],
                    properties: [
                        new OA\Property(property: 'profile_picture', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث الصورة بنجاح'),
            new OA\Response(response: 422, description: 'الملف غير صالح'),
        ]
    )]
    public function updateProfilePicture(UpdateProfilePictureRequest $request, int $id): JsonResponse
    {
        try {
            $newPath = $this->userService->updateProfilePicture($id, $request->getProfilePictureFile());

            return $this->successResponse('تم تحديث الصورة الشخصية بنجاح.', [
                'profile_picture_path' => $newPath,
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث الصورة: '.$e->getMessage(), [], 422);
        }
    }
}
