<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherPackage extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'voucher_packages';

    protected $primaryKey = 'package_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'created_by',
        'package_name',
        'package_color',
        'package_balance',
        'package_uptime',
        'package_validity',
        'package_voucher_count',
        'package_voucher_price',
        'package_default_cash_discount',
        'package_default_credit_discount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'package_balance' => 'decimal:2',
            'package_voucher_count' => 'integer',
            'package_voucher_price' => 'decimal:2',
            'package_default_cash_discount' => 'decimal:2',
            'package_default_credit_discount' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(VoucherPackageDiscount::class, 'package_id', 'package_id');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'package_id', 'package_id');
    }

    public function saleInvoiceItems(): HasMany
    {
        return $this->hasMany(SaleInvoiceItem::class, 'package_id', 'package_id');
    }
}
