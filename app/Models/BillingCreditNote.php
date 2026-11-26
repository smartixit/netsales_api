<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $credit_note_id
 * @property string $credit_note_number
 * @property Carbon|null $issued_at
 * @property int $invoice_id
 * @property int $billing_account_id
 * @property int $currency_id
 * @property string $status
 * @property string $reason
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property int $applied_to_receivable_minor
 * @property int $refundable_minor
 * @property int $refunded_minor
 * @property-read BillingInvoice $invoice
 * @property-read Currency $currency
 * @property-read Collection<int, BillingCreditNoteLine> $lines
 * @property-read Collection<int, BillingRefund> $refunds
 */
class BillingCreditNote extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_credit_notes';

    protected $primaryKey = 'credit_note_id';

    protected $fillable = [
        'credit_note_number',
        'idempotency_key',
        'invoice_id',
        'billing_account_id',
        'currency_id',
        'status',
        'reason',
        'accounting_treatment',
        'subtotal_minor',
        'tax_minor',
        'total_minor',
        'applied_to_receivable_minor',
        'refundable_minor',
        'refunded_minor',
        'issued_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'applied_to_receivable_minor' => 'integer',
            'refundable_minor' => 'integer',
            'refunded_minor' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $note): void {
            if ($note->getRawOriginal('status') === 'DRAFT') {
                return;
            }

            $allowed = ['status', 'refunded_minor', 'updated_at'];
            if (array_diff(array_keys($note->getDirty()), $allowed) !== []) {
                throw new LogicException('الإشعار الدائن الصادر غير قابل لتغيير قيمته أو عملته أو بنوده.');
            }

            if ($note->isDirty('status')) {
                $next = (string) $note->status;
                $current = (string) $note->getRawOriginal('status');
                $transitions = [
                    'ISSUED' => ['PARTIALLY_REFUNDED', 'REFUNDED'],
                    'PARTIALLY_REFUNDED' => ['REFUNDED'],
                ];
                if (! in_array($next, $transitions[$current] ?? [], true)) {
                    throw new LogicException('انتقال حالة الإشعار الدائن غير مسموح.');
                }
            }
        });

        static::deleting(function (): void {
            throw new LogicException('الإشعارات الدائنة لا تحذف بعد تسجيلها.');
        });
    }

    /** @return BelongsTo<BillingInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id', 'invoice_id');
    }

    /** @return BelongsTo<BillingAccount, $this> */
    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id', 'billing_account_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id');
    }

    /** @return HasMany<BillingCreditNoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BillingCreditNoteLine::class, 'credit_note_id', 'credit_note_id');
    }

    /** @return HasMany<BillingRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(BillingRefund::class, 'credit_note_id', 'credit_note_id');
    }
}
