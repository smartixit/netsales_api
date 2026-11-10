<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CurrencyExchangeRate extends Model
{
    use ExactDateSerialization;

    protected $table = 'currency_exchange_rates';
    protected $primaryKey = 'exchange_rate_id';

    protected $fillable = [
        'base_currency_id', 'quote_currency_id', 'rate_numerator',
        'rate_denominator', 'source', 'effective_at', 'expires_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_numerator' => 'integer',
            'rate_denominator' => 'integer',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('أسعار الصرف المسجلة غير قابلة للتعديل؛ سجّل سعراً جديداً بتاريخ سريان مستقل.');
        });

        static::deleting(function (): void {
            throw new LogicException('أسعار الصرف التاريخية لا تحذف.');
        });
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id', 'currency_id');
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_id', 'currency_id');
    }
}
