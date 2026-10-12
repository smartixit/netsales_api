<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestItem extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'request_items';

    protected $primaryKey = 'item_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'request_id',
        'package_id',
        'item_voucher_count',
    ];

    protected function casts(): array
    {
        return [
            'item_voucher_count' => 'integer',
        ];
    }

    // Relationships
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id', 'request_id');
    }

    public function voucherPackage(): BelongsTo
    {
        return $this->belongsTo(VoucherPackage::class, 'package_id', 'package_id');
    }

    // Calculations
    public function calculateSubtotal(?float $unitPrice = null): float
    {
        $price = $unitPrice ?? (float) ($this->voucherPackage?->package_voucher_price ?? 0);

        return (float) ($this->item_voucher_count * $price);
    }
}
