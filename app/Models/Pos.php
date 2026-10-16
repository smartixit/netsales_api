<?php

namespace App\Models;

use App\Enums\PosStatus;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pos extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'pos';

    protected $primaryKey = 'pos_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'owner_id',
        'pos_uuid',
        'pos_name',
        'pos_profile_logo',
        'pos_phone_number',
        'pos_email',
        'pos_address',
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
            'status' => PosStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    // Auto generate pos_uuid
    protected static function booted(): void
    {
        static::creating(function (self $pos): void {
            if (empty($pos->pos_uuid)) {
                $pos->pos_uuid = BusinessIdentifierGenerator::forPOS($pos->pos_id);
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

    public function networkPos(): HasMany
    {
        return $this->hasMany(NetworkPos::class, 'pos_id', 'pos_id');
    }

    public function networks(): BelongsToMany
    {
        return $this->belongsToMany(
            Network::class,
            'network_pos',
            'pos_id',
            'network_id'
        )->withTimestamps();
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(PosWallet::class, 'pos_id', 'pos_id');
    }

    public function voucherPackageDiscounts(): HasMany
    {
        return $this->hasMany(VoucherPackageDiscount::class, 'pos_id', 'pos_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'pos_id', 'pos_id');
    }

    public function saleInvoices(): HasMany
    {
        return $this->hasMany(SaleInvoice::class, 'pos_id', 'pos_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'pos_id', 'pos_id');
    }

    // Scopes
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', PosStatus::ACTIVE);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PosStatus::PENDING);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', PosStatus::REJECTED);
    }

    public function scopeInGovernorate(Builder $query, int $governorateId): Builder
    {
        return $query->where('governorate_id', $governorateId);
    }

    public function scopeInDirectorate(Builder $query, int $directorateId): Builder
    {
        return $query->where('directorate_id', $directorateId);
    }

    public function scopeLinkedToNetwork(Builder $query, int $networkId): Builder
    {
        return $query->whereHas('networkPos', fn (Builder $q): Builder => $q->where('network_id', $networkId)->where('status', true));
    }

    // Checkers
    public function isApproved(): bool
    {
        return $this->status === PosStatus::ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === PosStatus::PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === PosStatus::REJECTED;
    }

    public function isLinkedToNetwork(int $networkId): bool
    {
        return $this->networkPos()->where('network_id', $networkId)->where('status', true)->exists();
    }

    // Accessors
    // protected function logoUrl(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn (): string => $this->pos_profile_logo
    //             ? asset('storage/' . $this->pos_profile_logo)
    //             : asset('assets/images/default-pos.png')
    //     );
    // }
}
