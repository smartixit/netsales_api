<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    protected $keyType = 'int';

    protected $incrementing = true;

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

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'user_2fa_enabled' => 'boolean',
            'user_login_attempts' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    // Relationships
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
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
}
