<?php

namespace App\Models;

use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Services\Support\FileUploadService;
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

    /**
     * رابط الصورة الشخصية للمستخدم عبر خدمة التخزين السحابي.
     */
    public function getUserProfileLogoUrlAttribute(): ?string
    {
        if (empty($this->user_profile_logo)) {
            return null;
        }

        return app(FileUploadService::class)->url($this->user_profile_logo);
    }

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'user_2fa_enabled' => 'boolean',
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

    public function pos(): HasOne
    {
        return $this->hasOne(Pos::class, 'owner_id', 'user_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by', 'user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'user_id', 'user_id');
    }

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

    public function subscriptionPlanRequests(): HasMany
    {
        return $this->hasMany(SubscriptionPlanRequest::class, 'requested_by', 'user_id');
    }

    public function reviewedSubscriptionPlanRequests(): HasMany
    {
        return $this->hasMany(SubscriptionPlanRequest::class, 'reviewed_by', 'user_id');
    }

    public function providerSubscriptions(): HasMany
    {
        return $this->hasMany(ProviderSubscription::class, 'created_by', 'user_id');
    }

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

    public function isPending(): bool
    {
        return $this->status === UserStatus::PENDING || $this->status === UserStatus::PENDING->value;
    }

    public function isBanned(): bool
    {
        return $this->status === UserStatus::BANNED || $this->status === UserStatus::BANNED->value || $this->securityBans()->where(function (Builder $query): void {
            $query->whereNull('end_at')->orWhere('end_at', '>', now('Asia/Aden'));
        })->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleKey::SUPER_ADMIN);
    }

    public function hasRole(string|RoleKey $roleKey): bool
    {
        $key = $roleKey instanceof RoleKey ? $roleKey->value : $roleKey;

        return $this->role?->role_key === $key;
    }

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $upperKey = strtoupper($permissionKey);

        if ($this->permissions()->where(function ($q) use ($upperKey, $permissionKey) {
            $q->where('permission_key', $upperKey)->orWhere('permission_key', $permissionKey);
        })->exists()) {
            return true;
        }

        return $this->role?->permissions()->where(function ($q) use ($upperKey, $permissionKey) {
            $q->where('permission_key', $upperKey)->orWhere('permission_key', $permissionKey);
        })->exists() ?? false;
    }

    /**
     * Get all unique permission keys assigned to this user.
     *
     * @return array<int, string>
     */
    public function getAllPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::pluck('permission_key')->toArray();
        }

        $rolePermissions = $this->role ? $this->role->permissions()->pluck('permission_key')->toArray() : [];
        $directPermissions = $this->permissions()->pluck('permission_key')->toArray();

        return array_values(array_unique(array_merge($rolePermissions, $directPermissions)));
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->user_full_name ?: ($this->user_phone_number ?: 'مستخدم #'.$this->user_id)
        );
    }
}
