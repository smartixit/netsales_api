<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class VoucherPackage extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'voucher_packages';

    protected $primaryKey = 'package_id';

    protected $keyType = 'int';

    public $incrementing = true;

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

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function ensureActive(): void
    {
        if (! $this->isActive()) {
            throw ValidationException::withMessages([
                'package_id' => ["باقة كروت الإنترنت [{$this->package_name}] معطلة حالياً ولا يمكن استخدامها."],
            ]);
        }
    }

    // Calculations
    public function defaultDiscount(bool $isCash = true): float
    {
        return (float) ($isCash ? $this->package_default_cash_discount : $this->package_default_credit_discount);
    }

    public function calculateDefaultDiscountAmount(bool $isCash = true): float
    {
        $price = (float) $this->package_voucher_price;
        $discountPercent = $this->defaultDiscount($isCash);

        return round($price * ($discountPercent / 100), 2);
    }

    public function calculatePriceWithDefaultDiscount(bool $isCash = true): float
    {
        $price = (float) $this->package_voucher_price;
        $discountAmount = $this->calculateDefaultDiscountAmount($isCash);

        return max(0.0, round($price - $discountAmount, 2));
    }
}
