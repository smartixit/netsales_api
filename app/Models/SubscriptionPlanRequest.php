<?php

namespace App\Models;

use App\Enums\PaymentType;
use App\Enums\RequestStatus;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlanRequest extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'subscription_plan_requests';

    protected $primaryKey = 'request_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'plan_id',
        'requested_by',
        'request_uuid',
        'request_total_amount',
        'request_payment_type',
        'account_id',
        'transaction_reference',
        'transaction_receipt',
        'status',
        'rejection_reason',
        'reviewed_by',
        'review_date',
        'request_note',
    ];

    protected function casts(): array
    {
        return [
            'request_total_amount' => 'decimal:2',
            'request_payment_type' => PaymentType::class,
            'review_date' => 'datetime',
            'status' => RequestStatus::class,

        ];
    }

    // Auto generate request_uuid
    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (empty($request->request_uuid)) {
                $request->request_uuid = BusinessIdentifierGenerator::forSubscriptionPlanRequest($request->request_id);
            }
        });
    }

    // Relationships
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'plan_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'account_id', 'account_id');
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
}
