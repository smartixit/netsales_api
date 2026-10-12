<?php

namespace App\Http\Resources\API\V1\Network;

use App\Models\VoucherPackage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VoucherPackage
 */
class VoucherPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'package_id' => $this->package_id,
            'network_id' => $this->network_id,
            'package_name' => $this->package_name,
            'package_price' => (float) $this->package_price,
            'package_default_cash_discount' => (float) $this->package_default_cash_discount,
            'package_default_credit_discount' => (float) $this->package_default_credit_discount,
            'package_validity_days' => $this->package_validity_days,
            'package_hours' => $this->package_hours,
            'package_speed' => $this->package_speed,
            'package_quota_mb' => $this->package_quota_mb,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'discounts' => $this->whenLoaded('discounts', fn () => VoucherPackageDiscountResource::collection($this->discounts)),
        ];
    }
}
