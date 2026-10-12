<?php

namespace App\Http\Resources\API\V1\Geography;

use App\Models\Governorate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Governorate
 */
class GovernorateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'governorate_id' => $this->governorate_id,
            'governorate_name' => $this->governorate_name,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'directorates' => $this->whenLoaded('directorates', fn () => DirectorateResource::collection($this->directorates)),
        ];
    }
}
