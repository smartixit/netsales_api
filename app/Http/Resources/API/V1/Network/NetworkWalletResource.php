<?php

namespace App\Http\Resources\API\V1\Network;

use App\Models\NetworkWallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NetworkWallet
 */
class NetworkWalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'wallet_id' => $this->wallet_id,
            'network_id' => $this->network_id,
            'balance' => (float) $this->balance,
            'credit_limit' => (float) $this->credit_limit,
            'currency' => $this->currency?->value ?? $this->currency,
            'currency_label' => method_exists($this->currency, 'label') ? $this->currency->label() : null,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'logs' => $this->whenLoaded('logs', fn () => NetworkWalletLogResource::collection($this->logs)),
        ];
    }
}
