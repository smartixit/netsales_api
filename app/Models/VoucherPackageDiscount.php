<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
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

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeForPackageAndPos(Builder $query, int $packageId, int $posId): Builder
    {
        return $query->where('package_id', $packageId)->where('pos_id', $posId);
    }

    // Calculations
    public function effectiveDiscount(bool $isCash = true): float
    {
        return (float) ($isCash ? $this->discount_cash : $this->discount_credit);
    }

    public function calculateDiscountAmount(float $basePrice, bool $isCash = true): float
    {
        $discountPercent = $this->effectiveDiscount($isCash);

        return round($basePrice * ($discountPercent / 100), 2);
    }

    public function calculateFinalPrice(float $basePrice, bool $isCash = true): float
    {
        $discountAmount = $this->calculateDiscountAmount($basePrice, $isCash);

        return max(0.0, round($basePrice - $discountAmount, 2));
    }
}
