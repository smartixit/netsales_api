<?php

namespace App\Http\Resources\API\V1\BankAccount;

use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Models\BankAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'account_id' => $this->account_id,
            'network_id' => $this->network_id,
            'bank_name' => $this->bank_name,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'iban' => $this->iban,
            'swift_code' => $this->swift_code,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
        ];
    }
}
