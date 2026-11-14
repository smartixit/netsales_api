<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
