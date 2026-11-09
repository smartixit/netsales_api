<?php

namespace App\Services;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Models\Pos;
use App\Models\PosMembershipAuditLog;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة عضويات نقاط البيع والوكلاء المقيدة بنطاق المنشأة (Scoped POS Memberships).
 */
class PosMembershipService
{
    /**
     * جلب قائمة موظفي وعضويات نقطة البيع.
     */
    public function getMembers(int $posId, int $actorId): Collection
    {
        $this->authorizeActor($posId, $actorId);

        return PosUser::where('pos_id', $posId)
            ->with(['user', 'role'])
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * جلب الأدوار التشغيلية المسموح بإسنادها داخل نطاق نقطة البيع.
     */
    public function getAssignableRoles(int $posId, int $actorId): Collection
    {
        $this->authorizeActor($posId, $actorId);

        return Role::query()
            ->where('scope', RoleScope::POS->value)
            ->where('role_key', RoleKey::POS_USER->value)
            ->active()
            ->orderBy('role_name')
            ->get();
    }

    /**
     * إضافة عضو جديد لنقطة البيع عبر رقم هاتفه وتعيين دور مقيد بنطاق POS.
     */
    public function addMember(
        int $posId,
        string $phoneNumber,
        int $roleId,
        int $actorId,
        string $status = 'ACTIVE'
    ): PosUser {
        $this->authorizeActor($posId, $actorId);

        return DB::transaction(function () use ($posId, $phoneNumber, $roleId, $actorId, $status): PosUser {
            $user = User::where('user_phone_number', $phoneNumber)->first();
            if (! $user) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['لم يتم العثور على مستخدم مسجل برقم الهاتف المزود في المنظومة.'],
                ]);
            }

            // لا تسمح عضويات التشغيل بترقية عضو إلى وكيل أو محاسب أو دور منصة.
            $this->resolveAssignableRole($roleId);

            // منع التكرار
            $existing = PosUser::where('pos_id', $posId)
                ->where('user_id', $user->user_id)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['المستخدم مسجل بالفعل كعضو في نقطة البيع هذه.'],
                ]);
            }

            $member = PosUser::create([
                'pos_id' => $posId,
                'user_id' => $user->user_id,
                'role_id' => $roleId,
                'status' => in_array($status, ['ACTIVE', 'SUSPENDED', 'INVITED'], true) ? $status : 'ACTIVE',
                'last_event_seq' => 1,
            ]);

            // توثيق العملية في سجل التدقيق
            PosMembershipAuditLog::create([
                'pos_id' => $posId,
                'user_id' => $user->user_id,
                'event_seq' => 1,
                'action' => 'ASSIGNED',
                'actor_id' => $actorId,
                'old_state' => null,
                'new_state' => [
                    'role_id' => $roleId,
                    'status' => $member->status,
                    'reason' => 'إضافة عضوية بنقطة البيع بواسطة الوكيل',
                ],
                'created_at' => now('Asia/Aden'),
            ]);

            return $member->load(['user', 'role']);
        });
    }

    /**
     * تعديل الدور المقيد للعضو بنقطة البيع.
     */
    public function updateRole(int $posId, int $userId, int $newRoleId, int $actorId, ?string $reason = null): PosUser
    {
        $this->authorizeActor($posId, $actorId);

        return DB::transaction(function () use ($posId, $userId, $newRoleId, $actorId, $reason): PosUser {
            $pos = Pos::findOrFail($posId);
            if ((int) $pos->owner_id === $userId) {
                throw ValidationException::withMessages([
                    'role_id' => ['لا يمكن تغيير دور مالك نقطة البيع المعتمد.'],
                ]);
            }

            $member = PosUser::where('pos_id', $posId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $newRole = $this->resolveAssignableRole($newRoleId);

            $oldRoleId = $member->role_id;
            if ($oldRoleId === $newRoleId) {
                return $member->load(['user', 'role']);
            }

            $nextSeq = (int) $member->last_event_seq + 1;
            $member->update([
                'role_id' => $newRoleId,
                'last_event_seq' => $nextSeq,
            ]);

            PosMembershipAuditLog::create([
                'pos_id' => $posId,
                'user_id' => $userId,
                'event_seq' => $nextSeq,
                'action' => 'ROLE_CHANGED',
                'actor_id' => $actorId,
                'old_state' => ['role_id' => $oldRoleId],
                'new_state' => ['role_id' => $newRoleId, 'reason' => $reason ?? 'تعديل الصلاحيات والدور الوظيفي بنقطة البيع'],
                'created_at' => now('Asia/Aden'),
            ]);

            return $member->load(['user', 'role']);
        });
    }

    /**
     * تحديث حالة العضو بنقطة البيع (تفعيل / تجميد).
     */
    public function updateStatus(int $posId, int $userId, string $newStatus, int $actorId, ?string $reason = null): PosUser
    {
        $this->authorizeActor($posId, $actorId);

        if (! in_array($newStatus, ['ACTIVE', 'SUSPENDED', 'INVITED'], true)) {
            throw ValidationException::withMessages([
                'status' => ['حالة العضوية المزودة غير صحيحة.'],
            ]);
        }

        return DB::transaction(function () use ($posId, $userId, $newStatus, $actorId, $reason): PosUser {
            $pos = Pos::findOrFail($posId);
            if ((int) $pos->owner_id === $userId && $newStatus !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن تجميد أو إيقاف عضوية مالك نقطة البيع الرئيسي.'],
                ]);
            }

            $member = PosUser::where('pos_id', $posId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $member->status;
            if ($oldStatus === $newStatus) {
                return $member->load(['user', 'role']);
            }

            $nextSeq = (int) $member->last_event_seq + 1;
            $member->update([
                'status' => $newStatus,
                'last_event_seq' => $nextSeq,
            ]);

            PosMembershipAuditLog::create([
                'pos_id' => $posId,
                'user_id' => $userId,
                'event_seq' => $nextSeq,
                'action' => 'STATUS_CHANGED',
                'actor_id' => $actorId,
                'old_state' => ['status' => $oldStatus],
                'new_state' => ['status' => $newStatus, 'reason' => $reason ?? 'تعديل حالة تفعيل العضوية'],
                'created_at' => now('Asia/Aden'),
            ]);

            return $member->load(['user', 'role']);
        });
    }

    /**
     * حذف عضوية مستخدم من نقطة البيع.
     */
    public function removeMember(int $posId, int $userId, int $actorId, ?string $reason = null): bool
    {
        $this->authorizeActor($posId, $actorId);

        return DB::transaction(function () use ($posId, $userId, $actorId, $reason): bool {
            $pos = Pos::findOrFail($posId);
            if ((int) $pos->owner_id === $userId) {
                throw ValidationException::withMessages([
                    'user_id' => ['لا يمكن إزالة أو حذف عضوية مالك نقطة البيع المعتمد.'],
                ]);
            }

            $member = PosUser::where('pos_id', $posId)
                ->where('user_id', $userId)
                ->firstOrFail();

            $nextSeq = (int) $member->last_event_seq + 1;

            PosMembershipAuditLog::create([
                'pos_id' => $posId,
                'user_id' => $userId,
                'event_seq' => $nextSeq,
                'action' => 'REMOVED',
                'actor_id' => $actorId,
                'old_state' => [
                    'role_id' => $member->role_id,
                    'status' => $member->status,
                ],
                'new_state' => ['reason' => $reason ?? 'إنهاء العضوية وحذف الارتباط بنقطة البيع'],
                'created_at' => now('Asia/Aden'),
            ]);

            $member->delete();

            return true;
        });
    }

    /**
     * لا تقبل إلا POS_USER النشط؛ يمنع ذلك تصعيد الامتيازات عبر العضويات.
     */
    protected function resolveAssignableRole(int $roleId): Role
    {
        $role = Role::query()->find($roleId);

        if (
            ! $role
            || ! $role->isActive()
            || $role->scope !== RoleScope::POS
            || $role->role_key !== RoleKey::POS_USER->value
        ) {
            throw ValidationException::withMessages([
                'role_id' => ['الدور المحدد غير مسموح به لعضوية نقطة البيع. يسمح حالياً بدور مستخدم نقطة البيع التشغيلي فقط.'],
            ]);
        }

        return $role;
    }

    /**
     * التحقق من تخويل الفاعل لإدارة عضويات نقطة البيع.
     */
    protected function authorizeActor(int $posId, int $actorId): void
    {
        $actor = User::findOrFail($actorId);

        if ($actor->isSuperAdmin()) {
            return;
        }

        $pos = Pos::findOrFail($posId);

        $isOwner = (int) $pos->owner_id === $actorId;
        $hasAgentMembership = PosUser::where('pos_id', $posId)
            ->where('user_id', $actorId)
            ->where('status', 'ACTIVE')
            ->whereHas('role', fn ($q) => $q->where('role_key', 'POS_AGENT'))
            ->exists();

        if (! $isOwner && ! $hasAgentMembership) {
            throw ValidationException::withMessages([
                'actor' => ['غير مصرح لك بإدارة موظفي وعضويات نقطة البيع هذه (مطلوب صلاحية وكيل نقطة البيع).'],
            ]);
        }
    }
}
