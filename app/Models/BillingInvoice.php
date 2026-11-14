<?php

namespace App\Models;

use App\Enums\BillingInvoiceStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $invoice_id
 * @property string $invoice_number
 * @property string $invoice_type
 * @property int $billing_account_id
 * @property int|null $billing_subscription_id
 * @property int $currency_id
 * @property \App\Enums\BillingInvoiceStatus $status
 * @property \Illuminate\Support\Carbon|null $period_start
 * @property \Illuminate\Support\Carbon|null $period_end
 * @property \Illuminate\Support\Carbon $issued_on
 * @property \Illuminate\Support\Carbon $due_on
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property int $paid_minor
 * @property int $credited_minor
 * @property int $remaining_minor
 * @property array<string, mixed>|null $customer_snapshot
 * @property array<string, mixed>|null $pricing_snapshot
 * @property string|null $memo
 * @property \Illuminate\Support\Carbon|null $finalized_at
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property-read BillingAccount $billingAccount
 * @property-read Currency $currency
 * @property-read BillingSubscription|null $subscription
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BillingInvoiceLine> $lines
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BillingPayment> $payments
 */
class BillingInvoice extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_invoices';
    protected $primaryKey = 'invoice_id';

    protected $fillable = [
        'invoice_number', 'billing_account_id', 'billing_subscription_id', 'currency_id',
        'invoice_type', 'status', 'period_start', 'period_end', 'issued_on', 'due_on',
        'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'paid_minor', 'credited_minor',
        'remaining_minor', 'customer_snapshot', 'pricing_snapshot', 'memo',
        'finalized_at', 'paid_at', 'voided_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => BillingInvoiceStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'credited_minor' => 'integer',
            'remaining_minor' => 'integer',
            'customer_snapshot' => 'array',
            'pricing_snapshot' => 'array',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $invoice): void {
            if ($invoice->getRawOriginal('finalized_at') === null) {
                return;
            }

            $allowed = [
                'status', 'paid_minor', 'credited_minor', 'remaining_minor', 'paid_at',
                'voided_at', 'memo', 'updated_at',
            ];

            if (array_diff(array_keys($invoice->getDirty()), $allowed) !== []) {
                throw new LogicException('الفاتورة الصادرة غير قابلة لتغيير بنودها أو مبالغها أو عملتها أو لقطات شروطها.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('الفواتير لا تحذف؛ استخدم الإلغاء مع عكس القيد المالي.');
        });
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id', 'billing_account_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id', 'billing_subscription_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillingInvoiceLine::class, 'invoice_id', 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'invoice_id', 'invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(BillingCreditNote::class, 'invoice_id', 'invoice_id');
    }

    public function dunningAttempts(): HasMany
    {
        return $this->hasMany(BillingDunningAttempt::class, 'invoice_id', 'invoice_id');
    }

    public function scopeForAccount(Builder $query, int $id): Builder
    {
        return $query->where('billing_account_id', $id);
    }
}
