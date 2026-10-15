<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BackupJob extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'backup_jobs';

    protected $primaryKey = 'backup_job_id';

    protected $fillable = [
        'job_uuid',
        'backup_type',
        'status',
        'stage',
        'file_name',
        'file_size_bytes',
        'archive_sha256',
        'drive_file_id',
        'drive_web_view_link',
        'drive_folder_id',
        'manifest_summary',
        'extra',
        'error_message',
        'started_at',
        'finished_at',
        'duration_ms',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'manifest_summary' => 'array',
            'extra' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [BackupStatus::SUCCESS->value, BackupStatus::FAILED->value], true);
    }
}
