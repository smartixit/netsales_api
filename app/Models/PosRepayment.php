<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PosRepayment extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'pos_repayments';

    protected $primaryKey = 'repayment_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'pos_id',
        'repayment_uuid',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'repayment_date',
        'status',
        'created_by',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'reversal_repayment_id',
    ];

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'pos_id' => 'integer',
            'amount' => 'decimal:2',
            'repayment_date' => 'datetime',
            'reversed_at' => 'datetime',
            'created_by' => 'integer',
            'reversed_by' => 'integer',
            'reversal_repayment_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $repayment): void {
            if (empty($repayment->repayment_uuid)) {
                $repayment->repayment_uuid = 'RPY-'.strtoupper(Str::random(12));
            }
        });
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PosRepaymentAllocation::class, 'repayment_id', 'repayment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by', 'user_id');
    }

    public function isReversed(): bool
    {
        return $this->status === 'REVERSED';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }
}
