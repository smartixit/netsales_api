<?php

namespace App\Http\Resources\API\V1\Network;

use App\Enums\NetworkStatus;
use App\Http\Resources\API\V1\Geography\DirectorateResource;
use App\Models\Network;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Network
 */
class NetworkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'network_id' => $this->network_id,
            'directorate_id' => $this->directorate_id,
            'network_name' => $this->network_name,
            'network_phone' => $this->network_phone,
            'network_address' => $this->network_address,
            'network_logo' => $this->network_logo,
            'network_currency' => $this->network_currency,
            'status' => $this->status instanceof NetworkStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof NetworkStatus
                ? $this->status->label()
                : (NetworkStatus::tryFrom((string) $this->status)?->label()),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'directorate' => $this->whenLoaded('directorate', fn () => new DirectorateResource($this->directorate)),
            'wallet' => $this->whenLoaded('wallet', fn () => new NetworkWalletResource($this->wallet)),
            'packages' => $this->whenLoaded('packages', fn () => VoucherPackageResource::collection($this->packages)),
        ];
    }
}
