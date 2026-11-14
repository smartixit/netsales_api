<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $revenue_schedule_id
 * @property int $invoice_id
 * @property int|null $invoice_line_id
 * @property int $currency_id
 * @property string $revenue_account_code
 * @property int $total_minor
 * @property int $recognized_minor
 * @property \Illuminate\Support\Carbon|null $last_recognized_on
 * @property string $status
 * @property \Illuminate\Support\Carbon $service_start_on
 * @property \Illuminate\Support\Carbon $service_end_on
 * @property \Illuminate\Support\Carbon|null $last_recognized_on
 * @property string $status
 * @property-read BillingInvoice $invoice
 * @property-read BillingInvoiceLine|null $invoiceLine
 * @property-read Currency $currency
 */
class BillingRevenueRecognitionSchedule extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_revenue_recognition_schedules';
    protected $primaryKey = 'revenue_schedule_id';

    protected $fillable = [
        'invoice_id',
        'invoice_line_id',
        'currency_id',
        'revenue_account_code',
        'total_minor',
        'recognized_minor',
        'service_start_on',
        'service_end_on',
        'last_recognized_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_minor' => 'integer',
            'recognized_minor' => 'integer',
            'service_start_on' => 'date',
            'service_end_on' => 'date',
            'last_recognized_on' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id', 'invoice_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(BillingInvoiceLine::class, 'invoice_line_id', 'invoice_line_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id');
    }
}
