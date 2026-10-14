<?php

namespace App\Http\Resources\API\V1\Expense;

use App\Models\ExpenseAttachment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpenseAttachment
 */
class ExpenseAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'attachment_id' => $this->attachment_id,
            'expense_id' => $this->expense_id,
            'attachment_name' => $this->attachment_name,
            'attachment_path' => $this->attachment_path,
            'attachment_size' => $this->attachment_size,
            'attachment_mime' => $this->attachment_mime,
            'file_url' => $this->file_url,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
        ];
    }
}
