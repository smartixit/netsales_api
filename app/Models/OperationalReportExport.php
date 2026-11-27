<?php

namespace App\Models;

use App\Enums\OperationalReportExportStatus;
use App\Enums\OperationalReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $report_export_id
 * @property string $export_uuid
 * @property int $network_id
 * @property int|null $pos_id
 * @property int $requested_by
 * @property OperationalReportType $report_type
 * @property array<string, mixed> $filters
 * @property OperationalReportExportStatus $status
 * @property string|null $artifact_path
 * @property string|null $artifact_sha256
 * @property int|null $artifact_size
 * @property int $record_count
 * @property string|null $failure_code
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 */
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

    /** @return BelongsTo<Network, $this> */
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    /** @return BelongsTo<Pos, $this> */
    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }
}
