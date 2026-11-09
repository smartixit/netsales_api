<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\API\V1\RBAC\RoleResource;
use App\Services\PosMembershipService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosMembershipController extends BaseController
{
    public function __construct(
        protected PosMembershipService $membershipService
    ) {}

    /**
     * عرض قائمة أعضاء وموظفي نقطة البيع.
     */
    public function index(int $posId): JsonResponse
    {
        try {
            $actorId = (int) $this->currentUserId();
            $members = $this->membershipService->getMembers($posId, $actorId);

            return $this->successResponse('تم استرجاع قائمة أعضاء وموظفي نقطة البيع بنجاح.', $members);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب أعضاء نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض الأدوار المسموح بإسنادها إلى أعضاء نقطة البيع.
     */
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
    public function store(Request $request, int $posId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_phone_number' => 'required|string|max:20',
                'role_id' => 'required|integer',
                'status' => 'nullable|string|in:ACTIVE,SUSPENDED,INVITED',
            ]);

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
    public function updateRole(Request $request, int $posId, int $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role_id' => 'required|integer',
                'reason' => 'nullable|string|max:255',
            ]);

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
    public function updateStatus(Request $request, int $posId, int $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:ACTIVE,SUSPENDED,INVITED',
                'reason' => 'nullable|string|max:255',
            ]);

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
    public function destroy(Request $request, int $posId, int $userId): JsonResponse
    {
        try {
            $actorId = (int) $this->currentUserId();
            $reason = $request->input('reason');
            $this->membershipService->removeMember($posId, $userId, $actorId, $reason);

            return $this->successResponse('تم إنهاء وإزالة عضوية المستخدم من نقطة البيع بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إزالة عضوية نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }
}
