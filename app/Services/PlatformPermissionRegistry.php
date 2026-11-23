<?php

namespace App\Services;

/**
 * Defines which unscoped permission keys may be granted to PLATFORM identities.
 *
 * The user_permissions pivot has no tenant discriminator. Tenant operational
 * permissions must therefore never be converted into global platform grants.
 * PLATFORM roles may see networks and POS records for support purposes, but
 * those shared keys are deliberately read-only.
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
