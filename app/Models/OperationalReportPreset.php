<?php

namespace App\Models;

use App\Enums\OperationalReportType;
use Illuminate\Database\Eloquent\Model;

final class OperationalReportPreset extends Model
{
    protected $table = 'operational_report_presets';

    protected $primaryKey = 'report_preset_id';

    protected $fillable = [
        'preset_uuid',
        'user_id',
        'network_id',
        'pos_id',
        'scope_key',
        'report_type',
        'name',
        'preset_schema_version',
        'report_schema_version',
        'criteria',
        'presentation',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'network_id' => 'integer',
            'pos_id' => 'integer',
            'preset_schema_version' => 'integer',
            'report_schema_version' => 'integer',
            'report_type' => OperationalReportType::class,
            'criteria' => 'array',
            'presentation' => 'array',
        ];
    }
}
