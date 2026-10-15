<?php

namespace App\Http\Resources\API\V1\Expense;

use App\Models\ExpenseItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpenseItem
 */
class ExpenseItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item_id' => $this->item_id,
            'expense_id' => $this->expense_id,
            'item_statement' => $this->item_statement,
            'item_amount' => (float) $this->item_amount,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            
        ];
    }
}
