<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAccountContact extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_account_contacts';

    protected $primaryKey = 'contact_id';

    protected $fillable = [
        'billing_account_id',
        'name',
        'email',
        'phone',
        'is_primary',
        'receives_invoices',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'receives_invoices' => 'boolean',
        ];
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id', 'billing_account_id');
    }
}