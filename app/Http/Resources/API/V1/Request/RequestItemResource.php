<?php

namespace App\Http\Resources\API\V1\Request;

use App\Http\Resources\API\V1\Network\VoucherPackageResource;
use App\Models\RequestItem;
use Carbon\Carbon;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestItem
 */
class RequestItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(HttpRequest $request): array
    {
        return [
            'item_id' => $this->item_id,
            'request_id' => $this->request_id,
            'package_id' => $this->package_id,
            'item_voucher_count' => (int) $this->item_voucher_count,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'package' => $this->whenLoaded('package', fn () => new VoucherPackageResource($this->package)),
        ];
    }
}
