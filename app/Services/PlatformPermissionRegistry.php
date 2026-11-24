<?php

namespace App\Services;

/**
 * Defines which unscoped permission keys may be granted to PLATFORM identities.
 *
 * The user_permissions pivot has no tenant discriminator. Tenant membership
 * permissions must therefore never be converted into global platform grants.
 * Cross-tenant actions are allowlisted explicitly below; network wallet
 * treasury operations are owned by the PLATFORM root, while network and POS
 * browsing remains read-only for support.
 */
final class PlatformPermissionRegistry
{
    public const MODULE_KEYS = [
        'auth',
        'users',
        'roles',
        'governorates',
        'directorates',
        'billing',
        'support',
        'notifications',
        'system_logs',
        'settings',
        'backups',
        'sms_settings',
        'scheduled_tasks',
        'dashboard',
        'bank_accounts',
    ];

    public const PLATFORM_PERMISSION_KEYS = [
        'networks.approve',
        'networks.reject',
        'pos.approve',
        'pos.reject',
        'network_wallets.view',
        'network_wallets.create',
        'network_wallets.edit',
        'network_wallets.deposit',
        'network_wallets.withdraw',
    ];

    public const SHARED_READ_PERMISSION_KEYS = [
        'networks.view',
        'pos.view',
    ];

    public function allows(string $permissionKey): bool
    {
        if (in_array($permissionKey, self::SHARED_READ_PERMISSION_KEYS, true)
            || in_array($permissionKey, self::PLATFORM_PERMISSION_KEYS, true)
        ) {
            return true;
        }

        $separator = strpos($permissionKey, '.');
        if ($separator === false) {
            return false;
        }

        $moduleKey = substr($permissionKey, 0, $separator);

        return in_array($moduleKey, self::MODULE_KEYS, true);
    }
}
