<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleInvoice extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'sale_invoices';

    protected $primaryKey = 'invoice_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'pos_id',
        'invoice_uuid',
        'invoice_sale_type',
        'invoice_total_amount',
        'invoice_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_total_amount' => 'decimal:2',
            'invoice_date' => 'datetime',
        ];
    }

    // Auto generate invoice_uuid
    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            if (empty($invoice->invoice_uuid)) {
                $invoice->invoice_uuid = BusinessIdentifierGenerator::forSaleInvoice($invoice->invoice_id);
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

    public function items(): HasMany
    {
        return $this->hasMany(SaleInvoiceItem::class, 'invoice_id', 'invoice_id');
    }

    // Scopes
    public function scopeForPos(Builder $query, int $posId): Builder
    {
        return $query->where('pos_id', $posId);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    // Calculations
    public function recalculateTotal(): void
    {
        $total = (float) $this->items()->sum('item_total_amount');
        $this->update(['invoice_total_amount' => $total]);
    }
}
