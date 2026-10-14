<?php

namespace App\Models;

use App\Enums\SettingCategory;
use App\Enums\ValueType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'system_settings';

    protected $primaryKey = 'setting_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'setting_category',
        'setting_key',
        'setting_value_type',
        'setting_value',
        'setting_desc',
    ];

    protected function casts(): array
    {
        return [
            'setting_category' => SettingCategory::class,
            'setting_value_type' => ValueType::class,
        ];
    }

    // Scopes
    public function scopeCategory(Builder $query, SettingCategory|string $category): Builder
    {
        return $query->where('setting_category', $category instanceof SettingCategory ? $category->value : $category);
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('setting_key', $key);
    }

    // Accessors
    protected function typedValue(): Attribute
    {
        return Attribute::make(
            get: function (): mixed {
                $value = $this->setting_value;
                if ($value === null) {
                    return null;
                }

                return match ($this->setting_value_type) {
                    ValueType::INTEGER => (int) $value,
                    ValueType::DECIMAL => (float) $value,
                    ValueType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    ValueType::JSON => json_decode((string) $value, true) ?? $value,
                    default => (string) $value,
                };
            }
        );
    }

    // Static Helpers
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->byKey($key)->first();

        return $setting ? $setting->typed_value : $default;
    }
}
