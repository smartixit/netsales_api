<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'expenses';

    protected $primaryKey = 'expense_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'expense_uuid',
        'expense_total_amount',
        'expense_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'datetime',
            'expense_total_amount' => 'decimal:2',
        ];
    }

    // Auto generate expense_uuid
    protected static function booted(): void
    {
        static::creating(function (self $expense): void {
            if (empty($expense->expense_uuid)) {
                $expense->expense_uuid = BusinessIdentifierGenerator::forExpense($expense->expense_id);
            }
        });
    }

    // Relationships
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class, 'expense_id', 'expense_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class, 'expense_id', 'expense_id');
    }

    // Scopes
    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    // Calculations
    public function recalculateTotal(): void
    {
        $total = (float) $this->items()->sum('item_amount');
        $this->update(['expense_total_amount' => $total]);
    }
}
