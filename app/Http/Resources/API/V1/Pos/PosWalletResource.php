<?php

namespace App\Http\Resources\API\V1\Pos;

use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Models\PosWallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosWallet
 */
class PosWalletResource extends JsonResource
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
            'pos_id' => $this->pos_id,
            'wallet_uuid' => $this->wallet_uuid,
            'wallet_balance' => (float) $this->wallet_balance,
            'status' => (bool) $this->status,
            'accounting_status' => $this->getAccountingStatus(),
            'accounting_status_label' => $this->getAccountingStatusLabel(),
            'is_overdrawn' => $this->isOverdrawn(),
            'debit_amount' => $this->getDebitAmount(),
            'credit_amount' => $this->getCreditAmount(),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'pos' => $this->whenLoaded('pos', fn () => new PosResource($this->pos)),
            'logs' => $this->whenLoaded('logs', fn () => PosWalletLogResource::collection($this->logs)),
        ];
    }
}
