<?php

namespace App\Services;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Models\Network;
use App\Models\NetworkMembershipAuditLog;
use App\Models\NetworkUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\BillingEntitlementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة عضويات شبكات الإنترنت المقيدة بنطاق الشبكة (Scoped Network Memberships).
 */
class NetworkMembershipService
{
    public function __construct(
        private BillingEntitlementService $billingEntitlements
    ) {}

    /**
     * جلب قائمة أعضاء الشبكة مع أدوارهم وبياناتهم الشخصية.
     */
    public function getMembers(int $networkId, int $actorId): Collection
    {
        $this->authorizeActor($networkId, $actorId);

        $ownerId = (int) Network::query()->whereKey($networkId)->value('owner_id');

        return NetworkUser::where('network_id', $networkId)
            ->with(['user', 'role'])
            ->orderBy('id', 'asc')
            ->get()
            ->each(fn (NetworkUser $member) => $member->setAttribute(
                'is_owner',
                (int) $member->user_id === $ownerId
            ));
    }

    /**
     * جلب الأدوار التشغيلية المسموح بإسنادها داخل هذا النطاق.
     */
    public function getAssignableRoles(int $networkId, int $actorId): Collection
    {
        $this->authorizeActor($networkId, $actorId);

        return Role::query()
            ->where('scope', RoleScope::NETWORK->value)
            ->where('role_key', RoleKey::NETWORK_USER->value)
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
        $this->authorizeActor($networkId, $actorId);

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

            // لا تسمح عضويات التشغيل بترقية عضو إلى مالك أو محاسب أو دور منصة.
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

            $member = NetworkUser::create([
                'network_id' => $networkId,
                'user_id' => $user->user_id,
                'role_id' => $roleId,
                'status' => $normalizedStatus,
                'last_event_seq' => 1,
            ]);

            // توثيق العملية في سجل التدقيق
            NetworkMembershipAuditLog::create([
                'network_id' => $networkId,
                'user_id' => $user->user_id,
                'event_seq' => 1,
                'action' => 'ASSIGNED',
                'actor_id' => $actorId,
                'old_state' => null,
                'new_state' => [
                    'role_id' => $roleId,
                    'status' => $member->status,
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
        $this->authorizeActor($networkId, $actorId);

        return DB::transaction(function () use ($networkId, $userId, $newRoleId, $actorId, $reason): NetworkUser {
            $network = Network::findOrFail($networkId);
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

            $nextSeq = (int) $member->last_event_seq + 1;
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
        $this->authorizeActor($networkId, $actorId);

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

            $oldStatus = $member->status;
            if ($oldStatus === $newStatus) {
                return $member->load(['user', 'role']);
            }

            if (! in_array($oldStatus, ['ACTIVE', 'INVITED'], true)
                && in_array($newStatus, ['ACTIVE', 'INVITED'], true)) {
                $this->assertNetworkMemberQuota($networkId, (int) $network->owner_id);
            }

            $nextSeq = (int) $member->last_event_seq + 1;
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
        $this->authorizeActor($networkId, $actorId);

        return DB::transaction(function () use ($networkId, $userId, $actorId, $reason): bool {
            $network = Network::findOrFail($networkId);
            if ((int) $network->owner_id === $userId) {
                throw ValidationException::withMessages([
                    'user_id' => ['لا يمكن إزالة أو حذف عضوية مالك الشبكة الرئيسي من الشبكة.'],
                ]);
            }

            $member = NetworkUser::where('network_id', $networkId)
                ->where('user_id', $userId)
                ->firstOrFail();

            $nextSeq = (int) $member->last_event_seq + 1;

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
     * لا تقبل إلا NETWORK_USER النشط؛ يمنع ذلك تصعيد الامتيازات عبر العضويات.
     */
    protected function resolveAssignableRole(int $roleId): Role
    {
        $role = Role::query()->find($roleId);

        if (
            ! $role
            || ! $role->isActive()
            || $role->scope !== RoleScope::NETWORK
            || $role->role_key !== RoleKey::NETWORK_USER->value
        ) {
            throw ValidationException::withMessages([
                'role_id' => ['الدور المحدد غير مسموح به لعضوية الشبكة. يسمح حالياً بدور مستخدم الشبكة التشغيلي فقط.'],
            ]);
        }

        return $role;
    }

    /**
     * التحقق من تخويل الفاعل لإدارة عضويات الشبكة.
     */
    protected function authorizeActor(int $networkId, int $actorId): void
    {
        $actor = User::findOrFail($actorId);

        if ($actor->isSuperAdmin()) {
            return;
        }

        $network = Network::findOrFail($networkId);

        $isOwner = (int) $network->owner_id === $actorId;
        $hasOwnerMembership = NetworkUser::where('network_id', $networkId)
            ->where('user_id', $actorId)
            ->where('status', 'ACTIVE')
            ->whereHas('role', fn ($q) => $q->where('role_key', 'NETWORK_OWNER'))
            ->exists();

        if (! $isOwner && ! $hasOwnerMembership) {
            throw ValidationException::withMessages([
                'actor' => ['غير مصرح لك بإدارة موظفي وعضويات هذه الشبكة (مطلوب صلاحية مالك الشبكة).'],
            ]);
        }
    }
}
