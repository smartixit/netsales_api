<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseItem extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'expense_items';

    protected $primaryKey = 'item_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'expense_id',
        'item_amount',
        'item_statement',
    ];

    protected function casts(): array
    {
        return [
            'item_amount' => 'decimal:2',
        ];
    }

    // Relationships
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id', 'expense_id');
    }

    // Scopes
    public function scopeForExpense(Builder $query, int $expenseId): Builder
    {
        return $query->where('expense_id', $expenseId);
    }
}
