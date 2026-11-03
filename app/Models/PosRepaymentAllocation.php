<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosRepaymentAllocation extends Model
{
    use ExactDateSerialization;

    protected $table = 'pos_repayment_allocations';

    protected $primaryKey = 'allocation_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'repayment_id',
        'invoice_id',
        'network_id',
        'pos_id',
        'allocated_amount',
        'status',
        'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'repayment_id' => 'integer',
            'invoice_id' => 'integer',
            'network_id' => 'integer',
            'pos_id' => 'integer',
            'allocated_amount' => 'decimal:2',
            'allocated_at' => 'datetime',
        ];
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(PosRepayment::class, 'repayment_id', 'repayment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class, 'invoice_id', 'invoice_id');
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isReversed(): bool
    {
        return $this->status === 'REVERSED';
    }
}
