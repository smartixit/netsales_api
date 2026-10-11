<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherPackageDiscount extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'voucher_package_discounts';

    protected $primaryKey = 'discount_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'package_id',
        'pos_id',
        'created_by',
        'discount_cash',
        'discount_credit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_cash' => 'decimal:2',
            'discount_credit' => 'decimal:2',
            'status' => 'boolean',
        ];
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

    public function voucherPackage(): BelongsTo
    {
        return $this->belongsTo(VoucherPackage::class, 'package_id', 'package_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
