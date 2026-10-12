<?php

namespace App\Http\Resources\API\V1\Setting;

use App\Enums\SettingCategory;
use App\Enums\ValueType;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SystemSetting
 */
class SystemSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'setting_id' => $this->setting_id,
            'setting_category' => $this->setting_category instanceof SettingCategory ? $this->setting_category->value : $this->setting_category,
            'setting_category_label' => $this->setting_category instanceof SettingCategory
                ? $this->setting_category->label()
                : (SettingCategory::tryFrom((string) $this->setting_category)?->label()),
            'setting_key' => $this->setting_key,
            'setting_value_type' => $this->setting_value_type instanceof ValueType ? $this->setting_value_type->value : $this->setting_value_type,
            'setting_value_type_label' => $this->setting_value_type instanceof ValueType
                ? $this->setting_value_type->label()
                : (ValueType::tryFrom((string) $this->setting_value_type)?->label()),
            'setting_value' => $this->setting_value,
            'setting_desc' => $this->setting_desc,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toDateTimeString() : null,
        ];
    }
}
