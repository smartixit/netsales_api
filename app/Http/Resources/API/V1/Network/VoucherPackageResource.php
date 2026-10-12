<?php

namespace App\Http\Resources\API\V1\Network;

use App\Http\Resources\API\V1\User\UserResource;
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
            'package_color' => $this->package_color,
            'package_balance' => (float) $this->package_balance,
            'package_uptime' => $this->package_uptime,
            'package_validity' => $this->package_validity,
            'package_voucher_count' => (int) $this->package_voucher_count,
            'package_voucher_price' => (float) $this->package_voucher_price,
            'package_default_cash_discount' => (float) $this->package_default_cash_discount,
            'package_default_credit_discount' => (float) $this->package_default_credit_discount,
            'status' => (bool) $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'discounts' => $this->whenLoaded('discounts', fn () => VoucherPackageDiscountResource::collection($this->discounts)),
        ];
    }
}
