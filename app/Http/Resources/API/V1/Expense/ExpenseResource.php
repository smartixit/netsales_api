<?php

namespace App\Http\Resources\API\V1\Expense;

use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'expense_id' => $this->expense_id,
            'network_id' => $this->network_id,
            'expense_uuid' => $this->expense_uuid,
            'expense_total_amount' => (float) $this->expense_total_amount,
            'expense_date' => $this->expense_date ? Carbon::parse($this->expense_date)->toDateTimeString() : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'items' => $this->whenLoaded('items', fn () => ExpenseItemResource::collection($this->items)),
            'attachments' => $this->whenLoaded('attachments', fn () => ExpenseAttachmentResource::collection($this->attachments)),
        ];
    }
}
