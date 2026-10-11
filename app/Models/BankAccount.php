<?php

namespace App\Models;

use App\Enums\BankAccountType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'bank_accounts';

    protected $primaryKey = 'account_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'bank_name',
        'bank_logo',
        'account_holder_name',
        'account_number',
        'account_type',
        'account_currency',
        'account_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'account_balance' => 'decimal:2',
            'account_type' => BankAccountType::class,
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function subscriptionPlanRequests(): HasMany
    {
        return $this->hasMany(SubscriptionPlanRequest::class, 'account_id', 'account_id');
    }
}
