<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\API\V1\RBAC\RoleResource;
use App\Services\NetworkMembershipService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NetworkMembershipController extends BaseController
{
    public function __construct(
        protected NetworkMembershipService $membershipService
    ) {}

    /**
     * عرض قائمة أعضاء وموظفي الشبكة.
     */
    public function index(int $networkId): JsonResponse
    {
        try {
            $actorId = (int) $this->currentUserId();
            $members = $this->membershipService->getMembers($networkId, $actorId);

            return $this->successResponse('تم استرجاع قائمة أعضاء وموظفي الشبكة بنجاح.', $members);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب أعضاء الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض الأدوار المسموح بإسنادها إلى أعضاء الشبكة.
     */
    public function assignableRoles(int $networkId): JsonResponse
    {
        try {
            $actorId = (int) $this->currentUserId();
            $roles = $this->membershipService->getAssignableRoles($networkId, $actorId);

            return $this->successResponse(
                'تم استرجاع أدوار أعضاء الشبكة المسموح بها بنجاح.',
                RoleResource::collection($roles)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب أدوار أعضاء الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إضافة عضو جديد للشبكة عبر رقم الهاتف وتعيين دور مقيد.
     */
    public function store(Request $request, int $networkId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_phone_number' => 'required|string|max:20',
                'role_id' => 'required|integer',
                'status' => 'nullable|string|in:ACTIVE,SUSPENDED,INVITED',
            ]);

            $actorId = (int) $this->currentUserId();
            $member = $this->membershipService->addMember(
                $networkId,
                $validated['user_phone_number'],
                (int) $validated['role_id'],
                $actorId,
                $validated['status'] ?? 'ACTIVE'
            );

            return $this->successResponse('تم إضافة العضو للشبكة بنجاح.', $member, 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إضافة العضو للشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تعديل الدور المقيد للعضو بالشبكة.
     */
    public function updateRole(Request $request, int $networkId, int $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role_id' => 'required|integer',
                'reason' => 'nullable|string|max:255',
            ]);

            $actorId = (int) $this->currentUserId();
            $member = $this->membershipService->updateRole(
                $networkId,
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
     * تعديل حالة تفعيل عضو الشبكة.
     */
    public function updateStatus(Request $request, int $networkId, int $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:ACTIVE,SUSPENDED,INVITED',
                'reason' => 'nullable|string|max:255',
            ]);

            $actorId = (int) $this->currentUserId();
            $member = $this->membershipService->updateStatus(
                $networkId,
                $userId,
                $validated['status'],
                $actorId,
                $validated['reason'] ?? null
            );

            return $this->successResponse('تم تعديل حالة تفعيل عضوية الشبكة بنجاح.', $member);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تعديل حالة العضو: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف عضوية المستخدم من الشبكة.
     */
    public function destroy(Request $request, int $networkId, int $userId): JsonResponse
    {
        try {
            $actorId = (int) $this->currentUserId();
            $reason = $request->input('reason');
            $this->membershipService->removeMember($networkId, $userId, $actorId, $reason);

            return $this->successResponse('تم إنهاء وإزالة عضوية المستخدم من الشبكة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إزالة عضوية الشبكة: '.$e->getMessage(), [], 422);
        }
    }
}
