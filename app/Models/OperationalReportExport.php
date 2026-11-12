<?php

namespace App\Models;

use App\Enums\OperationalReportExportStatus;
use App\Enums\OperationalReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalReportExport extends Model
{
    protected $table = 'operational_report_exports';

    protected $primaryKey = 'report_export_id';

    protected $fillable = [
        'export_uuid',
        'network_id',
        'pos_id',
        'requested_by',
        'report_type',
        'filters',
        'status',
        'artifact_path',
        'artifact_sha256',
        'artifact_size',
        'record_count',
        'failure_code',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'pos_id' => 'integer',
            'requested_by' => 'integer',
            'report_type' => OperationalReportType::class,
            'filters' => 'array',
            'status' => OperationalReportExportStatus::class,
            'artifact_size' => 'integer',
            'record_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }
}
