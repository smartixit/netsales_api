<?php

namespace App\Models;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Services\PlatformPermissionRegistry;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    use ExactDateSerialization;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'role_id',
        'user_full_name',
        'user_profile_logo',
        'user_phone_number',
        'user_password',
        'user_email',
        'user_2fa_enabled',
        'must_change_password',
        'user_login_attempts',
        'last_login_at',
        'status',
    ];

    protected $hidden = [
        'user_password',
    ];

    /**
     * Get the password for authentication.
     */
    public function getAuthPassword(): string
    {
        return $this->user_password;
    }

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'user_2fa_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'user_login_attempts' => 'integer',
            'last_login_at' => 'datetime',
            'user_password' => 'hashed',
        ];
    }

    // Relationships
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function networks(): HasMany
    {
        return $this->hasMany(Network::class, 'owner_id', 'user_id');
    }

    public function networkUsers(): HasMany
    {
        return $this->hasMany(NetworkUser::class, 'user_id', 'user_id');
    }

    public function pos(): HasOne
    {
        return $this->hasOne(Pos::class, 'owner_id', 'user_id');
    }

    public function posUsers(): HasMany
    {
        return $this->hasMany(PosUser::class, 'user_id', 'user_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by', 'user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'user_id', 'user_id');
    }

    /**
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class, 'user_id', 'user_id');
    }

    public function securityBans(): HasMany
    {
        return $this->hasMany(SecurityBan::class, 'user_id', 'user_id');
    }

    public function bansIssued(): HasMany
    {
        return $this->hasMany(SecurityBan::class, 'banned_by', 'user_id');
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'user_id', 'user_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'user_permissions',
            'user_id',
            'permission_id'
        )->withTimestamps();
    }

    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class, 'user_id', 'user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }

    public function systemLogs(): HasMany
    {
        return $this->hasMany(SystemLog::class, 'user_id', 'user_id');
    }

    public function approvedNetworks(): HasMany
    {
        return $this->hasMany(Network::class, 'approved_by', 'user_id');
    }

    public function rejectedNetworks(): HasMany
    {
        return $this->hasMany(Network::class, 'rejected_by', 'user_id');
    }

    public function approvedPos(): HasMany
    {
        return $this->hasMany(Pos::class, 'approved_by', 'user_id');
    }

    public function rejectedPos(): HasMany
    {
        return $this->hasMany(Pos::class, 'rejected_by', 'user_id');
    }

    public function createdNetworkWalletLogs(): HasMany
    {
        return $this->hasMany(NetworkWalletLog::class, 'created_by', 'user_id');
    }

    public function createdPosWalletLogs(): HasMany
    {
        return $this->hasMany(PosWalletLog::class, 'created_by', 'user_id');
    }

    public function createdRequests(): HasMany
    {
        return $this->hasMany(Request::class, 'created_by', 'user_id');
    }

    public function reviewedRequests(): HasMany
    {
        return $this->hasMany(Request::class, 'reviewed_by', 'user_id');
    }

    public function saleInvoices(): HasMany
    {
        return $this->hasMany(SaleInvoice::class, 'created_by', 'user_id');
    }

    // public function subscriptionPlanRequests(): HasMany
    // {
    //     return $this->hasMany(SubscriptionPlanRequest::class, 'requested_by', 'user_id');
    // }

    // public function reviewedSubscriptionPlanRequests(): HasMany
    // {
    //     return $this->hasMany(SubscriptionPlanRequest::class, 'reviewed_by', 'user_id');
    // }

    // public function providerSubscriptions(): HasMany
    // {
    //     return $this->hasMany(ProviderSubscription::class, 'user_id', 'user_id');
    // }

    public function supportTicketMessages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'sent_by', 'user_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::ACTIVE->value);
    }

    public function scopeStatus(Builder $query, string|UserStatus $status): Builder
    {
        $val = $status instanceof UserStatus ? $status->value : $status;

        return $query->where('status', $val);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('user_full_name', 'like', "%{$term}%")
                ->orWhere('user_phone_number', 'like', "%{$term}%")
                ->orWhere('user_email', 'like', "%{$term}%");
        });
    }

    public function scopePhone(Builder $query, string $phone): Builder
    {
        return $query->where('user_phone_number', $phone);
    }

    public function scopeEmail(Builder $query, string $email): Builder
    {
        return $query->where('user_email', $email);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', UserStatus::PENDING->value);
    }

    public function scopeBanned(Builder $query): Builder
    {
        return $query->where('status', UserStatus::BANNED->value);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithRole(Builder $query, string|RoleKey $roleKey): Builder
    {
        $key = $roleKey instanceof RoleKey ? $roleKey->value : $roleKey;

        return $query->whereHas('role', fn (Builder $q): Builder => $q->where('role_key', $key));
    }

    // Business Checkers
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE || $this->status === UserStatus::ACTIVE->value;
    }

    public function ensureActive(): void
    {
        if ($this->isBanned()) {
            throw ValidationException::withMessages([
                'user_id' => ['حساب المستخدم محظور أمنياً، لا يمكن تنفيذ هذه العملية.'],
            ]);
        }

        if ($this->isPending()) {
            throw ValidationException::withMessages([
                'user_id' => ['حساب المستخدم قيد المراجعة حالياً.'],
            ]);
        }

        if (! $this->isActive()) {
            throw ValidationException::withMessages([
                'user_id' => ['حساب المستخدم غير مفعّل.'],
            ]);
        }

    }

    public function isPending(): bool
    {
        return $this->status === UserStatus::PENDING || $this->status === UserStatus::PENDING->value;
    }

    public function isBanned(): bool
    {
        return $this->status === UserStatus::BANNED || $this->status === UserStatus::BANNED->value || $this->securityBans()->where(function ($query): void {
            $query->whereNull('end_at')->orWhere('end_at', '>', now('Asia/Aden'));
        })->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->isActive()
            && $this->role_scope === RoleScope::PLATFORM->value
            && $this->role?->role_key === RoleKey::SUPER_ADMIN->value
            && $this->role?->scope === RoleScope::PLATFORM
            && $this->role->isActive();
    }

    public function hasRole(string|RoleKey $roleKey, ?int $tenantId = null): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $key = $roleKey instanceof RoleKey ? $roleKey->value : $roleKey;

        // A PLATFORM identity cannot inherit tenant roles from membership rows.
        if ($this->role_scope === RoleScope::PLATFORM->value) {
            return $this->role?->role_key === $key
                && $this->role->isActive();
        }

        if ($this->role?->role_key === $key && $this->role->isActive()) {
            return true;
        }

        $networkQuery = $this->networkUsers()
            ->where('status', 'ACTIVE')
            ->whereHas('role', fn ($query) => $query
                ->where('role_key', $key)
                ->where('scope', RoleScope::NETWORK->value)
                ->where('status', true));

        if ($tenantId) {
            $networkQuery->where('network_id', $tenantId);
        }

        if ($networkQuery->exists()) {
            return true;
        }

        $posQuery = $this->posUsers()
            ->where('status', 'ACTIVE')
            ->whereHas('role', fn ($query) => $query
                ->where('role_key', $key)
                ->where('scope', RoleScope::POS->value)
                ->where('status', true));

        if ($tenantId) {
            $posQuery->where('pos_id', $tenantId);
        }

        return $posQuery->exists();
    }
    public function hasPermission(string $permissionKey, ?int $tenantId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $upperKey = strtoupper($permissionKey);

        // user_permissions has no tenant discriminator. The generated
        // users.role_scope column is the authoritative identity boundary,
        // even when the referenced role has been soft-deleted.
        if ($this->role_scope === RoleScope::PLATFORM->value) {
            if (! app(PlatformPermissionRegistry::class)->allows($permissionKey)) {
                return false;
            }

            if (! $this->isActive() || ! $this->role?->isActive()) {
                return false;
            }

            $directGrant = $this->permissions()
                ->where(function ($query) use ($upperKey, $permissionKey): void {
                    $query->where('permissions.permission_key', $upperKey)
                        ->orWhere('permissions.permission_key', $permissionKey);
                })
                ->where('permissions.status', true)
                ->exists();

            if ($directGrant) {
                return true;
            }

            $platformRoleGrant = $this->role->permissions()
                ->where(function ($query) use ($upperKey, $permissionKey): void {
                    $query->where('permissions.permission_key', $upperKey)
                        ->orWhere('permissions.permission_key', $permissionKey);
                })
                ->where('permissions.status', true)
                ->exists();

            return $platformRoleGrant;
        }

        $hasNetPerm = $this->networkUsers()
            ->where('status', 'ACTIVE')
            ->when($tenantId, fn ($query) => $query->where('network_id', $tenantId))
            ->whereHas('role', function ($roleQuery) use ($upperKey, $permissionKey): void {
                $roleQuery->where('scope', RoleScope::NETWORK->value)
                    ->where('status', true)
                    ->whereHas('permissions', function ($permissionQuery) use ($upperKey, $permissionKey): void {
                        $permissionQuery
                            ->where(function ($query) use ($upperKey, $permissionKey): void {
                                $query->where('permissions.permission_key', $upperKey)
                                    ->orWhere('permissions.permission_key', $permissionKey);
                            })
                            ->where('permissions.status', true);
                    });
            })
            ->exists();

        if ($hasNetPerm) {
            return true;
        }

        return $this->posUsers()
            ->where('status', 'ACTIVE')
            ->when($tenantId, fn ($query) => $query->where('pos_id', $tenantId))
            ->whereHas('role', function ($roleQuery) use ($upperKey, $permissionKey): void {
                $roleQuery->where('scope', RoleScope::POS->value)
                    ->where('status', true)
                    ->whereHas('permissions', function ($permissionQuery) use ($upperKey, $permissionKey): void {
                        $permissionQuery
                            ->where(function ($query) use ($upperKey, $permissionKey): void {
                                $query->where('permissions.permission_key', $upperKey)
                                    ->orWhere('permissions.permission_key', $permissionKey);
                            })
                            ->where('permissions.status', true);
                    });
            })
            ->exists();
    }
    /**
     * Return active operational memberships for client workspace routing.
     *
     * @return array<int, array{scope: string, tenant_id: int, role_key: string, role_name: string}>
     */
    public function getWorkspaceRoles(): array
    {
        if (! $this->isActive() || $this->role_scope === RoleScope::PLATFORM->value) {
            return [];
        }

        $networkRoles = $this->networkUsers()
            ->where('status', 'ACTIVE')
            ->with('role.permissions')
            ->get()
            ->map(static fn (NetworkUser $membership): ?array => (
                $membership->role === null
                || ! $membership->role->isActive()
                || $membership->role->scope !== RoleScope::NETWORK
            )
                ? null
                : [
                    'scope' => 'NETWORK',
                    'tenant_id' => (int) $membership->network_id,
                    'role_key' => (string) $membership->role->role_key,
                    'role_name' => (string) $membership->role->role_name,
                    'permissions' => $membership->role->permissions
                        ->where('status', true)
                        ->pluck('permission_key')
                        ->values()
                        ->all(),
                ])
            ->filter()
            ->values();

        $posRoles = $this->posUsers()
            ->where('status', 'ACTIVE')
            ->with('role.permissions')
            ->get()
            ->map(static fn (PosUser $membership): ?array => (
                $membership->role === null
                || ! $membership->role->isActive()
                || $membership->role->scope !== RoleScope::POS
            )
                ? null
                : [
                    'scope' => 'POS',
                    'tenant_id' => (int) $membership->pos_id,
                    'role_key' => (string) $membership->role->role_key,
                    'role_name' => (string) $membership->role->role_name,
                    'permissions' => $membership->role->permissions
                        ->where('status', true)
                        ->pluck('permission_key')
                        ->values()
                        ->all(),
                ])
            ->filter()
            ->values();

        return $networkRoles->concat($posRoles)->all();
    }

    /**
     * Get unique permissions for the authenticated user, including active tenant roles.
     * This is a client capability union only; authorization remains tenant-scoped on the server.
     *
     * @return array<int, string>
     */
    public function getAllPermissions(): array
    {
        if (! $this->isActive()) {
            return [];
        }

        if ($this->isSuperAdmin()) {
            return Permission::query()
                ->where('status', true)
                ->pluck('permission_key')
                ->all();
        }

        // Platform identities expose only platform role + direct user grants.
        // They never inherit capability unions from operational memberships.
        if ($this->role_scope === RoleScope::PLATFORM->value) {
            if ($this->role === null
                || ! $this->role->isActive()
                || $this->role->scope !== RoleScope::PLATFORM
            ) {
                return [];
            }

            $rolePermissions = $this->role->permissions()
                ->where('permissions.status', true)
                ->pluck('permissions.permission_key')
                ->all();

            $directPermissions = $this->permissions()
                ->where('permissions.status', true)
                ->pluck('permissions.permission_key')
                ->all();

            return array_values(array_unique(array_merge($rolePermissions, $directPermissions)));
        }

        // Operational identities inherit permissions only from active
        // memberships whose role scope matches the membership table.
        $networkPermissions = $this->networkUsers()
            ->where('status', 'ACTIVE')
            ->with('role.permissions')
            ->get()
            ->flatMap(static fn (NetworkUser $membership) => (
                $membership->role !== null
                && $membership->role->isActive()
                && $membership->role->scope === RoleScope::NETWORK
            )
                ? $membership->role->permissions
                    ->where('status', true)
                    ->pluck('permission_key')
                : collect());

        $posPermissions = $this->posUsers()
            ->where('status', 'ACTIVE')
            ->with('role.permissions')
            ->get()
            ->flatMap(static fn (PosUser $membership) => (
                $membership->role !== null
                && $membership->role->isActive()
                && $membership->role->scope === RoleScope::POS
            )
                ? $membership->role->permissions
                    ->where('status', true)
                    ->pluck('permission_key')
                : collect());

        return array_values(array_unique(array_merge(
            $networkPermissions->all(),
            $posPermissions->all(),
        )));
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->user_full_name ?: ($this->user_phone_number ?: 'مستخدم #'.$this->user_id)
        );
    }
}
