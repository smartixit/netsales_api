<?php

namespace App\Http\Resources\API\V1\Network;

use App\Http\Resources\API\V1\Pos\PosResource;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\VoucherPackageDiscount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VoucherPackageDiscount
 */
class VoucherPackageDiscountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'discount_id' => $this->discount_id,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'package_id' => $this->package_id,
            'discount_cash' => (float) $this->discount_cash,
            'discount_credit' => (float) $this->discount_credit,
            'status' => (bool) $this->status,
            // Server-authoritative derived monetary values (Frontend Display-Only, AGENTS.md §34)
            // Computed from the linked package's catalog price when it is loaded.
            'original_package_price' => $this->relationLoaded('voucherPackage') && $this->voucherPackage
                ? (float) $this->voucherPackage->package_voucher_price
                : null,
            'effective_cash_price' => $this->relationLoaded('voucherPackage') && $this->voucherPackage
                ? round((float) $this->voucherPackage->package_voucher_price - (((float) $this->voucherPackage->package_voucher_price * (float) $this->discount_cash) / 100), 2)
                : null,
            'effective_credit_price' => $this->relationLoaded('voucherPackage') && $this->voucherPackage
                ? round((float) $this->voucherPackage->package_voucher_price - (((float) $this->voucherPackage->package_voucher_price * (float) $this->discount_credit) / 100), 2)
                : null,
            'cash_savings' => $this->relationLoaded('voucherPackage') && $this->voucherPackage
                ? round(((float) $this->voucherPackage->package_voucher_price * (float) $this->discount_cash) / 100, 2)
                : null,
            'credit_savings' => $this->relationLoaded('voucherPackage') && $this->voucherPackage
                ? round(((float) $this->voucherPackage->package_voucher_price * (float) $this->discount_credit) / 100, 2)
                : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'pos' => $this->whenLoaded('pos', fn () => new PosResource($this->pos)),
            'voucher_package' => $this->whenLoaded('voucherPackage', fn () => new VoucherPackageResource($this->voucherPackage)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
        ];
    }
}
