<?php

namespace App\Http\Resources\API\V1\Geography;

use App\Models\Directorate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Directorate
 */
class DirectorateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'directorate_id' => $this->directorate_id,
            'governorate_id' => $this->governorate_id,
            'directorate_name' => $this->directorate_name,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'governorate' => $this->whenLoaded('governorate', fn () => new GovernorateResource($this->governorate)),
        ];
    }
}
