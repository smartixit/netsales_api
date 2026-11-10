<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerJournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'journal_entry_id' => $this->journal_entry_id,
            'entry_number' => $this->entry_number,
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
            'entry_date' => $this->entry_date?->toDateTimeString(),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'description' => $this->description,
            'idempotency_key' => $this->idempotency_key,
            'lines' => LedgerJournalLineResource::collection($this->whenLoaded('lines')),
            'posted_at' => $this->posted_at?->toDateTimeString(),
        ];
    }
}
