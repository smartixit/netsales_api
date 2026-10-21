<?php

namespace App\Models;

use App\Enums\NetworkStatus;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Services\Support\FileUploadService;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Network extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'networks';

    protected $primaryKey = 'network_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'owner_id',
        'network_uuid',
        'network_name',
        'network_profile_logo',
        'network_phone_number',
        'network_email',
        'network_address',
        'governorate_id',
        'directorate_id',
        'approved_by',
        'rejected_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => NetworkStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    // Auto generate network_uuid
    protected static function booted(): void
    {
        static::creating(function (self $network): void {
            if (empty($network->network_uuid)) {
                $network->network_uuid = BusinessIdentifierGenerator::forNetwork($network->network_id);
            }
        });
    }

    // Relationships
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'user_id');
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'governorate_id', 'governorate_id');
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'directorate_id', 'directorate_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by', 'user_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(NetworkWallet::class, 'network_id', 'network_id');
    }

    public function networkPos(): HasMany
    {
        return $this->hasMany(NetworkPos::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsToMany
    {
        return $this->belongsToMany(
            Pos::class,
            'network_pos',
            'network_id',
            'pos_id'
        )->withTimestamps();
    }

    public function posWallets(): HasMany
    {
        return $this->hasMany(PosWallet::class, 'network_id', 'network_id');
    }

    public function voucherPackages(): HasMany
    {
        return $this->hasMany(VoucherPackage::class, 'network_id', 'network_id');
    }

    public function voucherPackageDiscounts(): HasMany
    {
        return $this->hasMany(VoucherPackageDiscount::class, 'network_id', 'network_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'network_id', 'network_id');
    }

    public function saleInvoices(): HasMany
    {
        return $this->hasMany(SaleInvoice::class, 'network_id', 'network_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'network_id', 'network_id');
    }

    public function subscriptionRequests(): HasMany
    {
        return $this->hasMany(SubscriptionPlanRequest::class, 'network_id', 'network_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ProviderSubscription::class, 'network_id', 'network_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'network_id', 'network_id');
    }

    // Scopes
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', NetworkStatus::ACTIVE);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $this->scopeApproved($query);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', NetworkStatus::PENDING);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', NetworkStatus::REJECTED);
    }

    public function scopeStatus(Builder $query, NetworkStatus|string $status): Builder
    {
        $val = $status instanceof NetworkStatus ? $status : NetworkStatus::tryFrom((string) $status);

        return $val ? $query->where('status', $val) : $query->where('status', $status);
    }

    public function scopeInGovernorate(Builder $query, int $governorateId): Builder
    {
        return $query->where('governorate_id', $governorateId);
    }

    public function scopeInDirectorate(Builder $query, int $directorateId): Builder
    {
        return $query->where('directorate_id', $directorateId);
    }

    // Checkers
    public function isApproved(): bool
    {
        return $this->status === NetworkStatus::ACTIVE;
    }

    public function isActive(): bool
    {
        return $this->isApproved();
    }

    public function ensureActive(): void
    {
        if (! $this->isActive()) {
            throw ValidationException::withMessages([
                'network_id' => ["الشبكة [{$this->network_name}] غير مفعلة أو غير معتمدة حالياً."],
            ]);
        }
    }

    public function isPending(): bool
    {
        return $this->status === NetworkStatus::PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === NetworkStatus::REJECTED;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()->where(function (Builder $query): void {
            $query->whereNull('subscription_end_date')->orWhere('subscription_end_date', '>', now('Asia/Aden'));
        })->exists();
    }

    public function smsSetting(): HasOne
    {
        return $this->hasOne(NetworkSmsSetting::class, 'network_id', 'network_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->network_profile_logo)) {
            return null;
        }

        return app(FileUploadService::class)->url($this->network_profile_logo);
    }
}
