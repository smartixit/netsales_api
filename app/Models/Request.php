<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'requests';

    protected $primaryKey = 'request_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'pos_id',
        'request_uuid',
        'request_total_amount',
        'request_date',
        'status',
        'created_by',
        'reviewed_by',
        'review_date',
        'request_note',
    ];

    protected function casts(): array
    {
        return [
            'request_total_amount' => 'decimal:2',
            'request_date' => 'datetime',
            'status' => RequestStatus::class,
            'review_date' => 'datetime',
        ];
    }

    // Auto generate request_uuid
    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (empty($request->request_uuid)) {
                $request->request_uuid = BusinessIdentifierGenerator::forRequest($request->request_id);
            }
        });
    }

    // Relationships
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'request_id', 'request_id');
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::REJECTED);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    public function scopeForPos(Builder $query, int $posId): Builder
    {
        return $query->where('pos_id', $posId);
    }

    // Checkers
    public function isPending(): bool
    {
        return $this->status === RequestStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === RequestStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === RequestStatus::REJECTED;
    }

    // Calculations
    public function recalculateTotal(): void
    {
        $total = $this->items()->with('voucherPackage')->get()->sum(function (RequestItem $item): float {
            return (float) ($item->item_voucher_count * ($item->voucherPackage?->package_voucher_price ?? 0));
        });

        $this->update(['request_total_amount' => $total]);
    }
}
