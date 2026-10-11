<?php

namespace App\Models;

use App\Enums\PosStatus;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
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

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'pos_uuid',
        'pos_name',
        'pos_profile_logo',
        'pos_holder_name',
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
}
