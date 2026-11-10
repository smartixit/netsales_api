<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingAccount extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'billing_accounts';

    protected $primaryKey = 'billing_account_id';

    protected $fillable = [
        'network_id',
        'account_number',
        'legal_name',
        'billing_email',
        'tax_number',
        'billing_address',
        'default_currency_id',
        'payment_terms_days',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['payment_terms_days' => 'integer'];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id', 'currency_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(BillingAccountContact::class, 'billing_account_id', 'billing_account_id');
    }
}