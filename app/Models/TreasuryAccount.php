<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryAccount extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'treasury_accounts';
    protected $primaryKey = 'treasury_account_id';

    protected $fillable = [
        'account_code', 'name', 'account_type', 'currency_id', 'ledger_account_id',
        'institution_name', 'account_reference', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id', 'ledger_account_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'treasury_account_id', 'treasury_account_id');
    }
}
