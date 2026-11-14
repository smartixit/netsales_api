<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $currency_id
 * @property string $code
 * @property string $name
 * @property int $decimal_places
 * @property bool $is_active
 */
class Currency extends Model
{
    use ExactDateSerialization;

    protected $table = 'currencies';

    protected $primaryKey = 'currency_id';

    protected $fillable = [
        'code',
        'numeric_code',
        'name',
        'native_name',
        'symbol',
        'decimal_places',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(BillingPrice::class, 'currency_id', 'currency_id');
    }

    public function billingAccounts(): HasMany
    {
        return $this->hasMany(BillingAccount::class, 'default_currency_id', 'currency_id');
    }
}