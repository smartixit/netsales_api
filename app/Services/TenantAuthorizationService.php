<?php

namespace App\Services;

use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\User;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * خدمة التحقق والتحكيم في عزل المستأجرين والصلاحيات المكانية (Tenant-Aware Authorization Service).
 *
 * تتولى التحقق الصارم من أن المستخدم يمتلك الصلاحية المطلوبة داخل نطاق المستأجر (Network أو POS)
 * وتمنع أي وصول عبر الحدود بين المستأجرين (Cross-Tenant Boundary Bypass).
 */
class TenantAuthorizationService
{
    /**
     * هل المستخدم يمتلك صلاحيات مدير النظام العام (Super Admin)؟
     */
    public function isSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * استخراج كافة معرفات الشبكات التي يملكها المستخدم أو يحمل فيها عضوية نشطة مع الصلاحية المطلوبة.
     * يعيد null في حال كان مديراً عاماً للنظام (بلا قيود).
     *
     * @return array<int>|null
     */
    public function getUserAuthorizedNetworkIds(User $user, ?string $permission = null): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        // 1. الشبكات المملوكة للمستخدم
        $ownedNetworkIds = Network::where('owner_id', $user->user_id)
            ->pluck('network_id')
            ->toArray();

        // 2. الشبكات التي يحمل فيها عضوية نشطة
        $memberQuery = NetworkUser::where('user_id', $user->user_id)
            ->where('status', 'ACTIVE');

        if ($permission) {
            $memberQuery->whereHas('role.permissions', function ($q) use ($permission) {
                $q->where('permission_key', $permission)
                    ->orWhere('permission_key', strtoupper($permission));
            });
        }

        $memberNetworkIds = $memberQuery->pluck('network_id')->toArray();

        return array_values(array_unique(array_merge($ownedNetworkIds, $memberNetworkIds)));
    }

    /**
     * استخراج كافة معرفات نقاط البيع التي يملكها المستخدم أو يحمل فيها عضوية نشطة.
     * يعيد null لمدير النظام العام.
     *
     * @return array<int>|null
     */
    public function getUserAuthorizedPosIds(User $user, ?string $permission = null): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        $ownedPosIds = Pos::where('owner_id', $user->user_id)
            ->pluck('pos_id')
            ->toArray();

        $memberQuery = PosUser::where('user_id', $user->user_id)
            ->where('status', 'ACTIVE');

        if ($permission) {
            $memberQuery->whereHas('role.permissions', function ($q) use ($permission) {
                $q->where('permission_key', $permission)
                    ->orWhere('permission_key', strtoupper($permission));
            });
        }

        $memberPosIds = $memberQuery->pluck('pos_id')->toArray();

        return array_values(array_unique(array_merge($ownedPosIds, $memberPosIds)));
    }

    /**
     * استخراج معرفات الشبكات المرتبطة تجارياً بنقاط البيع الخاصة بالمستخدم عبر رابط نشط.
     *
     * @return array<int>
     */
    public function getUserLinkedNetworkIdsForPos(User $user): array
    {
        $posIds = $this->getUserAuthorizedPosIds($user, 'voucher_packages.view');

        if ($posIds === null) {
            return Network::pluck('network_id')->toArray();
        }

        if (empty($posIds)) {
            return [];
        }

        return NetworkPos::whereIn('pos_id', $posIds)
            ->where('status', true)
            ->pluck('network_id')
            ->toArray();
    }

    /**
     * استخراج كافة معرفات الشبكات المسموح للمستخدم عرض باقاتها (شبكاته الخاصة + شبكات نقاط بيعه المرتبطة).
     *
     * @return array<int>|null
     */
    public function getViewableNetworkIdsForPackages(User $user): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        $managedNetworks = $this->getUserAuthorizedNetworkIds($user, 'voucher_packages.view') ?? [];
        $linkedNetworks = $this->getUserLinkedNetworkIdsForPos($user);

        return array_values(array_unique(array_merge($managedNetworks, $linkedNetworks)));
    }

    /**
     * استخراج نطاق رؤية الباقات للمستخدم (شبكات الإدارة وشبكات نقاط البيع المرتبطة).
     * يعيد null لمدير النظام العام.
     *
     * @return array{managed_network_ids: array<int>, pos_linked_network_ids: array<int>}|null
     */
    public function getPackageVisibilityScope(User $user): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        $managedNetworks = $this->getUserAuthorizedNetworkIds($user, 'voucher_packages.view') ?? [];
        $linkedNetworks = $this->getUserLinkedNetworkIdsForPos($user);
        $posLinkedOnly = array_values(array_diff($linkedNetworks, $managedNetworks));

        return [
            'managed_network_ids' => $managedNetworks,
            'pos_linked_network_ids' => $posLinkedOnly,
        ];
    }

    /**
     * استخراج نطاق رؤية الخصومات التعاقدية للمستخدم.
     * يعيد null لمدير النظام العام.
     *
     * @return array{managed_network_ids: array<int>, pos_ids: array<int>}|null
     */
    public function getDiscountVisibilityScope(User $user): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        $managedNetworks = $this->getUserAuthorizedNetworkIds($user, 'voucher_packages.view') ?? [];
        $authorizedPosIds = $this->getUserAuthorizedPosIds($user, 'voucher_packages.view') ?? [];

        return [
            'managed_network_ids' => $managedNetworks,
            'pos_ids' => $authorizedPosIds,
        ];
    }

    /**
     * هل المستخدم مخول بعرض باقات شبكة محددة؟
     */
    public function canViewNetworkPackages(User $user, int $networkId): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $viewableIds = $this->getViewableNetworkIdsForPackages($user);

        return $viewableIds !== null && in_array($networkId, $viewableIds, true);
    }

    /**
     * هل المستخدم مخول بإدارة باقات شبكة محددة (إنشاء/تعديل/حذف/استعادة) وفق الصلاحية؟
     */
    public function canManageNetworkPackages(User $user, int $networkId, string $permission): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // مالك الشبكة يملك الصلاحية الكاملة لإدارة باقات شبكته
        if (Network::where('network_id', $networkId)->where('owner_id', $user->user_id)->exists()) {
            return true;
        }

        // عضو الشبكة يجب أن يكون نشطاً ولديه الصلاحية المحددة في دوره
        return NetworkUser::where('network_id', $networkId)
            ->where('user_id', $user->user_id)
            ->where('status', 'ACTIVE')
            ->whereHas('role.permissions', function ($q) use ($permission) {
                $q->where('permission_key', $permission)
                    ->orWhere('permission_key', strtoupper($permission));
            })->exists();
    }

    /**
     * التحقق من تخويل عرض باقة محددة.
     */
    public function canViewPackage(User $user, VoucherPackage $package): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $networkId = (int) $package->network_id;

        // 1. إذا كان مديراً أو موظفاً في الشبكة
        if ($this->canManageNetworkPackages($user, $networkId, 'voucher_packages.view')) {
            return true;
        }

        // 2. إذا كان مستخدماً في نقطة بيع مرتبطة بالشبكة
        $posIds = $this->getUserAuthorizedPosIds($user, 'voucher_packages.view');
        if (! empty($posIds)) {
            $isLinked = NetworkPos::where('network_id', $networkId)
                ->whereIn('pos_id', $posIds)
                ->where('status', true)
                ->exists();

            // نقاط البيع تعرض فقط الباقات النشطة
            if ($isLinked && $package->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * التحقق من تخويل تعديل أو حذف باقة.
     */
    public function canManagePackage(User $user, VoucherPackage $package, string $permission): bool
    {
        return $this->canManageNetworkPackages($user, (int) $package->network_id, $permission);
    }

    /**
     * التحقق من تخويل عرض خصم مخصص محدد.
     */
    public function canViewDiscount(User $user, VoucherPackageDiscount $discount): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $networkId = (int) $discount->network_id;
        $posId = (int) $discount->pos_id;

        // 1. مزود الشبكة التابع لها الخصم
        if ($this->canManageNetworkPackages($user, $networkId, 'voucher_packages.view')) {
            return true;
        }

        // 2. نقطة البيع المعنية بالخصم حصراً (يُمنع منعاً باتاً رؤية خصومات نقاط بيع أخرى)
        $userPosIds = $this->getUserAuthorizedPosIds($user, 'voucher_packages.view') ?? [];
        if (in_array($posId, $userPosIds, true)) {
            // والتأكد من وجود ارتباط تجاري نشط
            return NetworkPos::where('network_id', $networkId)
                ->where('pos_id', $posId)
                ->where('status', true)
                ->exists();
        }

        return false;
    }

    /**
     * التحقق من تخويل إدارة وتعديل خصم مخصص.
     */
    public function canManageDiscount(User $user, VoucherPackageDiscount $discount, string $permission): bool
    {
        return $this->canManageNetworkPackages($user, (int) $discount->network_id, $permission);
    }

    /**
     * التحقق من تخويل إنشاء خصم تعاقدي استثنائي.
     */
    public function canCreateDiscount(User $user, int $networkId, int $posId, int $packageId): bool
    {
        if (! $this->canManageNetworkPackages($user, $networkId, 'voucher_packages.create')) {
            return false;
        }

        // التحقق من أن الباقة تنتمي لنفس الشبكة
        $packageValid = VoucherPackage::where('package_id', $packageId)
            ->where('network_id', $networkId)
            ->exists();

        if (! $packageValid) {
            throw ValidationException::withMessages([
                'package_id' => ['باقة الكروت المحددة لا تنتمي لمزود الشبكة المختار.'],
            ]);
        }

        // التحقق من وجود ارتباط تجاري نشط بين الشبكة ونقطة البيع
        $isLinked = NetworkPos::where('network_id', $networkId)
            ->where('pos_id', $posId)
            ->where('status', true)
            ->exists();

        if (! $isLinked) {
            throw ValidationException::withMessages([
                'pos_id' => ['لا يوجد ارتباط تجاري نشط بين مزود الشبكة ونقطة البيع المحددة.'],
            ]);
        }

        return true;
    }

    /**
     * التحقق الصارم من تخويل احتساب الخصم الفعلي (Effective Discount).
     */
    public function authorizeEffectiveDiscount(User $user, int $networkId, int $posId, int $packageId): void
    {
        if ($this->isSuperAdmin($user)) {
            return;
        }

        $userNetworkIds = $this->getUserAuthorizedNetworkIds($user, 'voucher_packages.view') ?? [];
        $userPosIds = $this->getUserAuthorizedPosIds($user, 'voucher_packages.view') ?? [];

        $isNetworkActor = in_array($networkId, $userNetworkIds, true);
        $isPosActor = in_array($posId, $userPosIds, true);

        if (! $isNetworkActor && ! $isPosActor) {
            throw new AuthorizationException('عذراً، ليس لديك الصلاحية لاحتساب الخصم لنقطة بيع أو شبكة خارج نطاقك المصرح.');
        }

        // التحقق من وجود ارتباط تجاري نشط
        $isLinked = NetworkPos::where('network_id', $networkId)
            ->where('pos_id', $posId)
            ->where('status', true)
            ->exists();

        if (! $isLinked) {
            throw new AuthorizationException('عذراً، لا يوجد ارتباط تجاري نشط بين نقطة البيع ومزود الشبكة المحدد.');
        }

        // التحقق من أن الباقة تتبع الشبكة
        $package = VoucherPackage::where('package_id', $packageId)
            ->where('network_id', $networkId)
            ->first();

        if (! $package) {
            throw ValidationException::withMessages([
                'package_id' => ['باقة الكروت المحددة غير موجودة أو لا تنتمي لمزود الشبكة المحدد.'],
            ]);
        }

        if (! $package->isActive()) {
            throw ValidationException::withMessages([
                'package_id' => ["باقة الكروت [{$package->package_name}] معطلة حالياً."],
            ]);
        }
    }
}
