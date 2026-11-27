<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Models\Pos;
use App\Models\PosMembershipAuditLog;
use App\Models\PosUser;
use App\Repositories\PosMembershipRepository;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة عضويات نقاط البيع والوكلاء المقيدة بنطاق الشبكة (Scoped POS Memberships).
 */
class PosMembershipService
{
    public function __construct(
        private TenantPermissionService $tenantPermissions,
        private PosMembershipRepository $membershipRepository
    ) {}

    /**
     * جلب قائمة موظفي وعضويات نقطة البيع.
     */
    public function getMembers(int $posId, int $actorId, array $filters = []): CursorPaginator
    {
        $this->authorizeActor($posId, $actorId, 'pos_members.view');

        $ownerId = (int) Pos::query()->whereKey($posId)->value('owner_id');
        $members = $this->membershipRepository->getMembersForPos($posId, $filters);

        foreach ($members->items() as $member) {
            $member->setAttribute('is_owner', (int) $member->user_id === $ownerId);
        }

        return $members;
    }

    /**
     * @return array{total:int,active:int,suspended:int,invited:int}
     */
    public function getMembersSummary(int $posId, int $actorId): array
    {
        $this->authorizeActor($posId, $actorId, 'pos_members.view');

        return $this->membershipRepository->getSummaryForPos($posId);
    }

    /**
     * جلب الأدوار التشغيلية المسموح بإسنادها داخل نطاق نقطة البيع.
     */
    public function getAssignableRoles(int $posId, int $actorId): Collection
    {
        $this->authorizeActor($posId, $actorId, 'pos_members.view');

        return Role::query()
            ->where('scope', RoleScope::POS->value)
            ->whereIn('role_key', [
                RoleKey::POS_USER->value,
                RoleKey::POS_ACCOUNTANT->value,
            ])
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
        $this->authorizeActor($posId, $actorId, 'pos_members.create');

        return DB::transaction(function () use ($posId, $phoneNumber, $roleId, $actorId, $status): PosUser {
            // Serialize all membership audit events for this POS.
            Pos::query()->lockForUpdate()->findOrFail($posId);

            $user = User::where('user_phone_number', $phoneNumber)->first();
            if (! $user) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['لم يتم العثور على مستخدم مسجل برقم الهاتف المزود في المنظومة.'],
                ]);
            }

            if ($user->role_scope === RoleScope::PLATFORM->value) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['لا يمكن إضافة حساب من نطاق المنصة إلى عضوية تشغيلية داخل نقطة بيع.'],
                ]);
            }

            // لا تسمح عضويات التشغيل بترقية عضو إلى وكيل أو دور منصة؛ المحاسب دور تشغيلي قابل للإسناد.
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

            $nextSeq = $this->nextAuditSequence($posId);

            $member = PosUser::create([
                'pos_id' => $posId,
                'user_id' => $user->user_id,
                'role_id' => $roleId,
                'status' => in_array($status, ['ACTIVE', 'SUSPENDED', 'INVITED'], true) ? $status : 'ACTIVE',
                'last_event_seq' => $nextSeq,
            ]);

            // توثيق العملية في سجل التدقيق
            PosMembershipAuditLog::create([
                'pos_id' => $posId,
                'user_id' => $user->user_id,
                'event_seq' => $nextSeq,
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
        $this->authorizeActor($posId, $actorId, 'pos_members.change_role');

        return DB::transaction(function () use ($posId, $userId, $newRoleId, $actorId, $reason): PosUser {
            $pos = Pos::query()->lockForUpdate()->findOrFail($posId);
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

            $nextSeq = $this->nextAuditSequence($posId);
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
        $this->authorizeActor($posId, $actorId, 'pos_members.change_status');

        if (! in_array($newStatus, ['ACTIVE', 'SUSPENDED', 'INVITED'], true)) {
            throw ValidationException::withMessages([
                'status' => ['حالة العضوية المزودة غير صحيحة.'],
            ]);
        }

        return DB::transaction(function () use ($posId, $userId, $newStatus, $actorId, $reason): PosUser {
            $pos = Pos::query()->lockForUpdate()->findOrFail($posId);
            if ((int) $pos->owner_id === $userId && $newStatus !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن تجميد أو إيقاف عضوية مالك نقطة البيع الرئيسي.'],
                ]);
            }

            $member = PosUser::where('pos_id', $posId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $member->status instanceof MembershipStatus
                ? $member->status->value
                : (string) $member->status;
            if ($oldStatus === $newStatus) {
                return $member->load(['user', 'role']);
            }

            $nextSeq = $this->nextAuditSequence($posId);
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
        $this->authorizeActor($posId, $actorId, 'pos_members.delete');

        return DB::transaction(function () use ($posId, $userId, $actorId, $reason): bool {
            $pos = Pos::query()->lockForUpdate()->findOrFail($posId);
            if ((int) $pos->owner_id === $userId) {
                throw ValidationException::withMessages([
                    'user_id' => ['لا يمكن إزالة أو حذف عضوية مالك نقطة البيع المعتمد.'],
                ]);
            }

            $member = PosUser::where('pos_id', $posId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $nextSeq = $this->nextAuditSequence($posId);

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
     * Reserve the next tenant-wide audit sequence. The caller must hold a
     * lock on the parent POS row until the transaction commits.
     */
    private function nextAuditSequence(int $posId): int
    {
        return (int) PosMembershipAuditLog::query()
            ->where('pos_id', $posId)
            ->max('event_seq') + 1;
    }

    /**
     * تقبل POS_USER وPOS_ACCOUNTANT النشطين فقط؛ يبقى POS_AGENT
     * محصوراً في هوية مالك نقطة البيع ولا يمكن منحه كعضوية فرعية.
     */
    protected function resolveAssignableRole(int $roleId): Role
    {
        $role = Role::query()->find($roleId);

        if (
            ! $role
            || ! $role->isActive()
            || $role->scope !== RoleScope::POS
            || ! in_array($role->role_key, [
                RoleKey::POS_USER->value,
                RoleKey::POS_ACCOUNTANT->value,
            ], true)
        ) {
            throw ValidationException::withMessages([
                'role_id' => ['الدور المحدد غير مسموح به لعضوية نقطة البيع. يسمح فقط بدوري مستخدم نقطة البيع ومحاسب نقطة البيع.'],
            ]);
        }

        return $role;
    }

    /**
     * التحقق من تخويل الفاعل لإدارة عضويات نقطة البيع.
     */
    protected function authorizeActor(int $posId, int $actorId, string $permission): void
    {
        $actor = User::query()->findOrFail($actorId);

        if (! $actor->isActive()) {
            throw ValidationException::withMessages([
                'actor' => ['لا يمكن لحساب غير نشط إدارة عضويات نقطة البيع.'],
            ]);
        }

        if ($actor->role_scope === RoleScope::PLATFORM->value) {
            throw ValidationException::withMessages([
                'actor' => ['حسابات نطاق المنصة لا تدير عضويات NETWORK أو POS.'],
            ]);
        }

        Pos::query()->findOrFail($posId);

        if (! $this->tenantPermissions->allowsPos($actor, $posId, $permission)) {
            throw ValidationException::withMessages([
                'actor' => ['لا تملك صلاحية العملية المطلوبة داخل نقطة البيع هذه.'],
            ]);
        }
    }
}
