<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $report_run_id
 * @property string $report_run_uuid
 * @property string $scope_type
 * @property int|null $network_id
 * @property int $requested_by
 * @property string $permission_key
 * @property string $report_key
 * @property string $definition_version
 * @property string $status
 * @property array<string, mixed> $filters
 * @property string $artifact_format
 * @property string|null $artifact_path
 * @property string|null $artifact_sha256
 * @property int $artifact_bytes
 * @property int $records_count
 * @property bool $is_truncated
 * @property array<string, mixed>|null $result_metadata
 * @property string|null $error_code
 * @property string|null $error_message
 * @property \\Illuminate\\Support\\Carbon $queued_at
 * @property \\Illuminate\\Support\\Carbon|null $started_at
 * @property \\Illuminate\\Support\\Carbon|null $completed_at
 * @property \\Illuminate\\Support\\Carbon|null $expires_at
 * @property \\Illuminate\\Support\\Carbon $created_at
 * @property \\Illuminate\\Support\\Carbon $updated_at
 */
class ReportRun extends Model
{
    protected $table = 'report_runs';

    protected $primaryKey = 'report_run_id';

    protected $fillable = [
        'report_run_uuid',
        'scope_type',
        'network_id',
        'requested_by',
        'permission_key',
        'report_key',
        'definition_version',
        'status',
        'filters',
        'artifact_format',
        'artifact_path',
        'artifact_sha256',
        'artifact_bytes',
        'records_count',
        'is_truncated',
        'result_metadata',
        'error_code',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'requested_by' => 'integer',
            'filters' => 'array',
            'artifact_bytes' => 'integer',
            'records_count' => 'integer',
            'is_truncated' => 'boolean',
            'result_metadata' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
