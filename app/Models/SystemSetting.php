<?php

namespace App\Models;

use App\Enums\ValueType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'system_settings';

    protected $primaryKey = 'setting_id';

    protected $keyType = 'int';

    protected $incrementing = true;

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
            'setting_value_type' => ValueType::class,
        ];
    }

    // Relationships
}
