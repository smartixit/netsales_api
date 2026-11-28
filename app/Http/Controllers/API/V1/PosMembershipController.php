<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Membership\IndexMembershipRequest;
use App\Http\Resources\API\V1\Membership\WorkspaceMembershipResource;
use App\Http\Requests\API\V1\Membership\RemoveMembershipRequest;
use App\Http\Requests\API\V1\Membership\StoreMembershipRequest;
use App\Http\Requests\API\V1\Membership\UpdateMembershipRoleRequest;
use App\Http\Requests\API\V1\Membership\UpdateMembershipStatusRequest;
use App\Http\Resources\API\V1\RBAC\RoleResource;
use App\Services\PosMembershipService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PosMembershipController extends BaseController
{
    public function __construct(
        protected PosMembershipService $membershipService
    ) {}

    /**
     * عرض قائمة أعضاء وموظفي نقطة البيع.
     */
    #[OA\Get(
        path: '/api/v1/pos/{posId}/members', summary: 'index', tags: ['Membership'], operationId: 'posmembership_index',
        parameters: [new OA\Parameter(name: 'posId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'cursor', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function index(IndexMembershipRequest $request, int $posId): JsonResponse
    {
        try {
            $actorId = (int) $this->currentUserId();
            $filters = $request->validated();
            $members = $this->membershipService->getMembers($posId, $actorId, $filters);
            $summary = $this->membershipService->getMembersSummary($posId, $actorId);

            return $this->successResponse(
                'تم استرجاع قائمة أعضاء وموظفي نقطة البيع بنجاح.',
                WorkspaceMembershipResource::collection($members),
                200,
                ['summary' => $summary]
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب أعضاء نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض الأدوار المسموح بإسنادها إلى أعضاء نقطة البيع.
     */
    #[OA\Get(
        path: '/api/v1/pos/{posId}/members/assignable-roles', summary: 'assignableRoles', tags: ['Membership'], operationId: 'posmembership_assignableRoles',
        parameters: [new OA\Parameter(name: 'posId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function assignableRoles(int $posId): JsonResponse
    {
        try {
            $actorId = (int) $this->currentUserId();
            $roles = $this->membershipService->getAssignableRoles($posId, $actorId);

            return $this->successResponse(
                'تم استرجاع أدوار أعضاء نقطة البيع المسموح بها بنجاح.',
                RoleResource::collection($roles)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب أدوار أعضاء نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إضافة عضو جديد لنقطة البيع عبر رقم الهاتف وتعيين دور مقيد.
     */
    #[OA\Post(
        path: '/api/v1/pos/{posId}/members', summary: 'store', tags: ['Membership'], operationId: 'posmembership_store',
        parameters: [new OA\Parameter(name: 'posId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['user_phone_number', 'role_id'], properties: [new OA\Property(property: 'user_phone_number', type: 'string'), new OA\Property(property: 'role_id', type: 'integer'), new OA\Property(property: 'status', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function store(StoreMembershipRequest $request, int $posId): JsonResponse
    {
        try {
            $validated = $request->validated();

            $actorId = (int) $this->currentUserId();
            $member = $this->membershipService->addMember(
                $posId,
                $validated['user_phone_number'],
                (int) $validated['role_id'],
                $actorId,
                $validated['status'] ?? 'ACTIVE'
            );

            return $this->successResponse('تم إضافة العضو لنقطة البيع بنجاح.', $member, 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إضافة العضو لنقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تعديل الدور المقيد للعضو بنقطة البيع.
     */
    #[OA\Put(
        path: '/api/v1/pos/{posId}/members/{userId}/role', summary: 'updateRole', tags: ['Membership'], operationId: 'posmembership_updateRole',
        parameters: [new OA\Parameter(name: 'posId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['role_id'], properties: [new OA\Property(property: 'role_id', type: 'integer'), new OA\Property(property: 'reason', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function updateRole(UpdateMembershipRoleRequest $request, int $posId, int $userId): JsonResponse
    {
        try {
            $validated = $request->validated();

            $actorId = (int) $this->currentUserId();
            $member = $this->membershipService->updateRole(
                $posId,
                $userId,
                (int) $validated['role_id'],
                $actorId,
                $validated['reason'] ?? null
            );

            return $this->successResponse('تم تحديث دور وصلاحيات العضو بنجاح.', $member);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث دور العضو: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تعديل حالة تفعيل عضو نقطة البيع.
     */
    #[OA\Put(
        path: '/api/v1/pos/{posId}/members/{userId}/status', summary: 'updateStatus', tags: ['Membership'], operationId: 'posmembership_updateStatus',
        parameters: [new OA\Parameter(name: 'posId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['status'], properties: [new OA\Property(property: 'status', type: 'string'), new OA\Property(property: 'reason', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function updateStatus(UpdateMembershipStatusRequest $request, int $posId, int $userId): JsonResponse
    {
        try {
            $validated = $request->validated();

            $actorId = (int) $this->currentUserId();
            $member = $this->membershipService->updateStatus(
                $posId,
                $userId,
                $validated['status'],
                $actorId,
                $validated['reason'] ?? null
            );

            return $this->successResponse('تم تعديل حالة تفعيل عضوية نقطة البيع بنجاح.', $member);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تعديل حالة العضو: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف عضوية المستخدم من نقطة البيع.
     */
    #[OA\Delete(
        path: '/api/v1/pos/{posId}/members/{userId}', summary: 'destroy', tags: ['Membership'], operationId: 'posmembership_destroy',
        parameters: [new OA\Parameter(name: 'posId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function destroy(RemoveMembershipRequest $request, int $posId, int $userId): JsonResponse
    {
        try {
            $actorId = (int) $this->currentUserId();
            $reason = $request->validated('reason');
            $this->membershipService->removeMember($posId, $userId, $actorId, $reason);

            return $this->successResponse('تم إنهاء وإزالة عضوية المستخدم من نقطة البيع بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إزالة عضوية نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }
}
