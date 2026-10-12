<?php

namespace App\Http\Resources\API\V1\Network;

use App\Models\NetworkWalletLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NetworkWalletLog
 */
class NetworkWalletLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'log_id' => $this->log_id,
            'wallet_id' => $this->wallet_id,
            'transaction_type' => $this->transaction_type?->value ?? $this->transaction_type,
            'transaction_type_label' => method_exists($this->transaction_type, 'label') ? $this->transaction_type->label() : null,
            'amount' => (float) $this->amount,
            'previous_balance' => (float) $this->previous_balance,
            'current_balance' => (float) $this->current_balance,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'note' => $this->note,
            'created_at' => $this->created_at? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'wallet' => $this->whenLoaded('wallet', fn () => new NetworkWalletResource($this->wallet)),
        ];
    }
}
