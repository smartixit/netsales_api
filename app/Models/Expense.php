<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $expense): void {
            if (empty($expense->expense_uuid)) {
                $expense->expense_uuid = BusinessIdentifierGenerator::forExpense($expense->expense_id);
            }
        });
    }
}
