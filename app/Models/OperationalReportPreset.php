<?php

namespace App\Models;

use App\Enums\OperationalReportType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $report_preset_id
 * @property string $preset_uuid
 * @property int $user_id
 * @property int $network_id
 * @property int|null $pos_id
 * @property string $scope_key
 * @property OperationalReportType $report_type
 * @property string $name
 * @property int $preset_schema_version
 * @property int $report_schema_version
 * @property array<string, mixed> $criteria
 * @property array<string, mixed> $presentation
 */
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
