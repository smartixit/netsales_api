<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingProduct extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'billing_products';

    protected $primaryKey = 'product_id';

    protected $fillable = [
        'product_key',
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(BillingPlan::class, 'product_id', 'product_id');
    }
}