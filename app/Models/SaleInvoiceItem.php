<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleInvoiceItem extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'sale_invoice_items';

    protected $primaryKey = 'item_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'invoice_id',
        'package_id',
        'voucher_price',
        'voucher_count',
        'item_discount',
        'item_total_amount',
    ];

    protected function casts(): array
    {
        return [
            'voucher_price' => 'decimal:2',
            'voucher_count' => 'integer',
            'item_discount' => 'decimal:2',
            'item_total_amount' => 'decimal:2',
        ];
    }

    // Relationships
    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class, 'invoice_id', 'invoice_id');
    }

    public function voucherPackage(): BelongsTo
    {
        return $this->belongsTo(VoucherPackage::class, 'package_id', 'package_id');
    }
}
