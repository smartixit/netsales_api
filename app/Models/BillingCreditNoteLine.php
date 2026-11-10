<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BillingCreditNoteLine extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_credit_note_lines';
    protected $primaryKey = 'credit_note_line_id';

    protected $fillable = ['credit_note_id', 'invoice_line_id', 'description', 'quantity_micros', 'amount_minor'];

    protected function casts(): array
    {
        return ['quantity_micros' => 'integer', 'amount_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        $assertDraft = static function (self $line): void {
            $note = BillingCreditNote::query()->find($line->credit_note_id);
            if (! $note || $note->status !== 'DRAFT') {
                throw new LogicException('لا يمكن تغيير بنود إشعار دائن بعد إصداره.');
            }
        };

        static::creating($assertDraft);
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(BillingCreditNote::class, 'credit_note_id', 'credit_note_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(BillingInvoiceLine::class, 'invoice_line_id', 'invoice_line_id');
    }
}
