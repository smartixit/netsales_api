<?php

namespace App\Http\Resources\API\V1\Network;

use App\Enums\NetworkStatus;
use App\Http\Resources\API\V1\Geography\DirectorateResource;
use App\Http\Resources\API\V1\Geography\GovernorateResource;
use App\Http\Resources\API\V1\User\UserResource;
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
            'network_uuid' => $this->network_uuid,
            'network_name' => $this->network_name,
            'network_profile_logo' => $this->network_profile_logo,
            'network_holder_name' => $this->network_holder_name,
            'network_phone_number' => $this->network_phone_number,
            'network_email' => $this->network_email,
            'governorate_id' => $this->governorate_id,
            'directorate_id' => $this->directorate_id,
            'network_address' => $this->network_address,
            'approved_by' => $this->approved_by,
            'rejected_by' => $this->rejected_by,
            'approved_at' => $this->approved_at ? Carbon::parse($this->approved_at)->toDateTimeString() : null,
            'rejected_at' => $this->rejected_at ? Carbon::parse($this->rejected_at)->toDateTimeString() : null,
            'rejection_reason' => $this->rejection_reason,
            'status' => $this->status instanceof NetworkStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof NetworkStatus
                ? $this->status->label()
                : (NetworkStatus::tryFrom((string) $this->status)?->label()),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'governorate' => $this->whenLoaded('governorate', fn () => new GovernorateResource($this->governorate)),
            'directorate' => $this->whenLoaded('directorate', fn () => new DirectorateResource($this->directorate)),
            'approved_by_user' => $this->whenLoaded('approvedBy', fn () => new UserResource($this->approvedBy)),
            'rejected_by_user' => $this->whenLoaded('rejectedBy', fn () => new UserResource($this->rejectedBy)),
            'wallet' => $this->whenLoaded('wallet', fn () => new NetworkWalletResource($this->wallet)),
            'voucher_packages' => $this->whenLoaded('voucherPackages', fn () => VoucherPackageResource::collection($this->voucherPackages)),
        ];
    }
}
