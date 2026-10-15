<?php

namespace App\Http\Resources\API\V1\SubscriptionPlan;

use App\Enums\ValueType;
use App\Models\Feature;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Feature
 */
class FeatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'feature_id' => $this->feature_id,
            'feature_name' => $this->feature_name,
            'feature_value_type' => $this->feature_value_type instanceof ValueType ? $this->feature_value_type->value : $this->feature_value_type,
            'feature_value_type_label' => $this->feature_value_type instanceof ValueType
                ? $this->feature_value_type->label()
                : (ValueType::tryFrom((string) $this->feature_value_type)?->label()),
            'feature_value' => $this->feature_value,
            'feature_desc' => $this->feature_desc,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            
        ];
    }
}
