<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Models\Network;
use App\Models\NetworkMembershipAuditLog;
use App\Models\NetworkUser;
use App\Repositories\NetworkMembershipRepository;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\BillingEntitlementService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة عضويات شبكات الإنترنت المقيدة بنطاق الشبكة (Scoped Network Memberships).
 */
class NetworkMembershipService
{
    public function __construct(
        private BillingEntitlementService $billingEntitlements,
        private TenantPermissionService $tenantPermissions,
        private NetworkMembershipRepository $membershipRepository
    ) {}

    /**
     * جلب قائمة أعضاء الشبكة مع أدوارهم وبياناتهم الشخصية.
     */
    /**
     * @param array<string, mixed> $filters
     * @return CursorPaginator<int, NetworkUser>
     */
    public function getMembers(int $networkId, int $actorId, array $filters = []): CursorPaginator
    {
        $this->authorizeActor($networkId, $actorId, 'network_members.view');

        $ownerId = (int) Network::query()->whereKey($networkId)->value('owner_id');
        $members = $this->membershipRepository->getMembersForNetwork($networkId, $filters);

        foreach ($members->items() as $member) {
            $member->setAttribute('is_owner', (int) $member->user_id === $ownerId);
        }

        return $members;
    }

    /**
     * @return array{total:int,active:int,suspended:int,invited:int}
     */
    public function getMembersSummary(int $networkId, int $actorId): array
    {
        $this->authorizeActor($networkId, $actorId, 'network_members.view');

        return $this->membershipRepository->getSummaryForNetwork($networkId);
    }

    /**
     * جلب الأدوار التشغيلية المسموح بإسنادها داخل هذا النطاق.
     */
    /** @return Collection<int, Role> */
    public function getAssignableRoles(int $networkId, int $actorId): Collection
    {
        $this->authorizeActor($networkId, $actorId, 'network_members.view');

        return Role::query()
            ->where('scope', RoleScope::NETWORK->value)
            ->whereIn('role_key', [
                RoleKey::NETWORK_USER->value,
                RoleKey::NETWORK_ACCOUNTANT->value,
            ])
            ->active()
            ->orderBy('role_name')
            ->get();
    }

    /**
     * إضافة عضو جديد للشبكة عبر رقم هاتفه وتعيين دور مقيد بنطاق NETWORK.
     */
    public function addMember(
        int $networkId,
        string $phoneNumber,
        int $roleId,
        int $actorId,
        string $status = 'ACTIVE'
    ): NetworkUser {
        $this->authorizeActor($networkId, $actorId, 'network_members.create');

        return DB::transaction(function () use ($networkId, $phoneNumber, $roleId, $actorId, $status): NetworkUser {
            // تسلسل حجز المقاعد داخل الشبكة لمنع تجاوز الحد بطلبات متزامنة.
            $network = Network::query()->lockForUpdate()->findOrFail($networkId);
            $normalizedStatus = in_array($status, ['ACTIVE', 'SUSPENDED', 'INVITED'], true)
                ? $status
                : 'ACTIVE';

            $user = User::where('user_phone_number', $phoneNumber)->first();
            if (! $user) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['لم يتم العثور على مستخدم مسجل برقم الهاتف المزود في المنظومة.'],
                ]);
            }

            if ($user->role_scope === RoleScope::PLATFORM->value) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['لا يمكن إضافة حساب من نطاق المنصة إلى عضوية تشغيلية داخل شبكة.'],
                ]);
            }

            // لا تسمح عضويات التشغيل بترقية عضو إلى مالك أو دور منصة؛ المحاسب دور تشغيلي قابل للإسناد.
            $this->resolveAssignableRole($roleId);

            // منع التكرار
            $existing = NetworkUser::where('network_id', $networkId)
                ->where('user_id', $user->user_id)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'user_phone_number' => ['المستخدم مسجل بالفعل كعضو في هذه الشبكة.'],
                ]);
            }

            if (in_array($normalizedStatus, ['ACTIVE', 'INVITED'], true)) {
                $this->assertNetworkMemberQuota($networkId, (int) $network->owner_id);
            }

            $nextSeq = $this->nextAuditSequence($networkId);

            $member = NetworkUser::create([
                'network_id' => $networkId,
                'user_id' => $user->user_id,
                'role_id' => $roleId,
                'status' => $normalizedStatus,
                'last_event_seq' => $nextSeq,
            ]);

            // توثيق العملية في سجل التدقيق
            NetworkMembershipAuditLog::create([
                'network_id' => $networkId,
                'user_id' => $user->user_id,
                'event_seq' => $nextSeq,
                'action' => 'ASSIGNED',
                'actor_id' => $actorId,
                'old_state' => null,
                'new_state' => [
                    'role_id' => $roleId,
                    'status' => $member->status->value,
                    'reason' => 'إضافة العضوية بواسطة مسؤول الشبكة',
                ],
                'created_at' => now('Asia/Aden'),
            ]);

            return $member->load(['user', 'role']);
        });
    }

    /**
     * تعديل الدور المقيد للعضو بالشبكة.
     */
    public function updateRole(int $networkId, int $userId, int $newRoleId, int $actorId, ?string $reason = null): NetworkUser
    {
        $this->authorizeActor($networkId, $actorId, 'network_members.change_role');

        return DB::transaction(function () use ($networkId, $userId, $newRoleId, $actorId, $reason): NetworkUser {
            $network = Network::query()->lockForUpdate()->findOrFail($networkId);
            if ((int) $network->owner_id === $userId) {
                throw ValidationException::withMessages([
                    'role_id' => ['لا يمكن تغيير دور مالك الشبكة الرئيسي للشبكة.'],
                ]);
            }

            $member = NetworkUser::where('network_id', $networkId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $newRole = $this->resolveAssignableRole($newRoleId);

            $oldRoleId = $member->role_id;
            if ($oldRoleId === $newRoleId) {
                return $member->load(['user', 'role']);
            }

            $nextSeq = $this->nextAuditSequence($networkId);
            $member->update([
                'role_id' => $newRoleId,
                'last_event_seq' => $nextSeq,
            ]);

            NetworkMembershipAuditLog::create([
                'network_id' => $networkId,
                'user_id' => $userId,
                'event_seq' => $nextSeq,
                'action' => 'ROLE_CHANGED',
                'actor_id' => $actorId,
                'old_state' => ['role_id' => $oldRoleId],
                'new_state' => ['role_id' => $newRoleId, 'reason' => $reason ?? 'تعديل الصلاحيات والدور الوظيفي'],
                'created_at' => now('Asia/Aden'),
            ]);

            return $member->load(['user', 'role']);
        });
    }

    /**
     * تحديث حالة العضو (تفعيل / تجميد).
     */
    public function updateStatus(int $networkId, int $userId, string $newStatus, int $actorId, ?string $reason = null): NetworkUser
    {
        $this->authorizeActor($networkId, $actorId, 'network_members.change_status');

        if (! in_array($newStatus, ['ACTIVE', 'SUSPENDED', 'INVITED'], true)) {
            throw ValidationException::withMessages([
                'status' => ['حالة العضوية المزودة غير صحيحة.'],
            ]);
        }

        return DB::transaction(function () use ($networkId, $userId, $newStatus, $actorId, $reason): NetworkUser {
            $network = Network::query()->lockForUpdate()->findOrFail($networkId);
            if ((int) $network->owner_id === $userId && $newStatus !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن تجميد أو إيقاف عضوية مالك الشبكة الرئيسي.'],
                ]);
            }

            $member = NetworkUser::where('network_id', $networkId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $member->status->value;
            if ($oldStatus === $newStatus) {
                return $member->load(['user', 'role']);
            }

            if (! in_array($oldStatus, ['ACTIVE', 'INVITED'], true)
                && in_array($newStatus, ['ACTIVE', 'INVITED'], true)) {
                $this->assertNetworkMemberQuota($networkId, (int) $network->owner_id);
            }

            $nextSeq = $this->nextAuditSequence($networkId);
            $member->update([
                'status' => $newStatus,
                'last_event_seq' => $nextSeq,
            ]);

            NetworkMembershipAuditLog::create([
                'network_id' => $networkId,
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
     * حذف عضوية مستخدم من الشبكة.
     */
    public function removeMember(int $networkId, int $userId, int $actorId, ?string $reason = null): bool
    {
        $this->authorizeActor($networkId, $actorId, 'network_members.delete');

        return DB::transaction(function () use ($networkId, $userId, $actorId, $reason): bool {
            $network = Network::query()->lockForUpdate()->findOrFail($networkId);
            if ((int) $network->owner_id === $userId) {
                throw ValidationException::withMessages([
                    'user_id' => ['لا يمكن إزالة أو حذف عضوية مالك الشبكة الرئيسي من الشبكة.'],
                ]);
            }

            $member = NetworkUser::where('network_id', $networkId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $nextSeq = $this->nextAuditSequence($networkId);

            NetworkMembershipAuditLog::create([
                'network_id' => $networkId,
                'user_id' => $userId,
                'event_seq' => $nextSeq,
                'action' => 'REMOVED',
                'actor_id' => $actorId,
                'old_state' => [
                    'role_id' => $member->role_id,
                    'status' => $member->status,
                ],
                'new_state' => ['reason' => $reason ?? 'إنهاء العضوية وحذف الارتباط بالشبكة'],
                'created_at' => now('Asia/Aden'),
            ]);

            $member->delete();

            return true;
        });
    }

    /**
     * Enforce the plan's concurrent active/invited network-member seat limit.
     * The network row is locked by the caller for the duration of its transaction.
     */
    private function assertNetworkMemberQuota(int $networkId, int $ownerId): void
    {
        $subscription = $this->billingEntitlements->activeSubscriptionForNetwork($networkId);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => ['يلزم وجود اشتراك نشط أو تجربة سارية قبل تفعيل أعضاء فريق الشبكة.'],
            ]);
        }

        $currentCount = NetworkUser::query()
            ->where('network_id', $networkId)
            ->where('user_id', '!=', $ownerId)
            ->whereIn('status', ['ACTIVE', 'INVITED'])
            ->count();

        $this->billingEntitlements->assertResourceLimit(
            $subscription,
            'max_network_members',
            $currentCount
        );
    }

    /**
     * Reserve the next tenant-wide audit sequence. The caller must hold a
     * lock on the parent network row until the transaction commits.
     */
    private function nextAuditSequence(int $networkId): int
    {
        return (int) NetworkMembershipAuditLog::query()
            ->where('network_id', $networkId)
            ->max('event_seq') + 1;
    }

    /**
     * تقبل NETWORK_USER وNETWORK_ACCOUNTANT النشطين فقط؛ يبقى NETWORK_OWNER
     * محصوراً في هوية مالك الشبكة ولا يمكن منحه كعضوية فرعية.
     */
    protected function resolveAssignableRole(int $roleId): Role
    {
        $role = Role::query()->find($roleId);

        if (
            ! $role
            || ! $role->isActive()
            || $role->scope !== RoleScope::NETWORK
            || ! in_array($role->role_key, [
                RoleKey::NETWORK_USER->value,
                RoleKey::NETWORK_ACCOUNTANT->value,
            ], true)
        ) {
            throw ValidationException::withMessages([
                'role_id' => ['الدور المحدد غير مسموح به لعضوية الشبكة. يسمح فقط بدوري مستخدم الشبكة ومحاسب الشبكة.'],
            ]);
        }

        return $role;
    }

    /**
     * التحقق من تخويل الفاعل لإدارة عضويات الشبكة.
     */
    protected function authorizeActor(int $networkId, int $actorId, string $permission): void
    {
        $actor = User::query()->findOrFail($actorId);

        if (! $actor->isActive()) {
            throw ValidationException::withMessages([
                'actor' => ['لا يمكن لحساب غير نشط إدارة عضويات الشبكة.'],
            ]);
        }

        if ($actor->role_scope === RoleScope::PLATFORM->value) {
            throw ValidationException::withMessages([
                'actor' => ['حسابات نطاق المنصة لا تدير عضويات NETWORK أو POS.'],
            ]);
        }

        Network::query()->findOrFail($networkId);

        if (! $this->tenantPermissions->allowsNetwork($actor, $networkId, $permission)) {
            throw ValidationException::withMessages([
                'actor' => ['لا تملك صلاحية العملية المطلوبة داخل هذه الشبكة.'],
            ]);
        }
    }
}
