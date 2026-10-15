<?php

namespace App\Http\Resources\API\V1\Backup;

use App\Models\BackupJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BackupJob
 */
class BackupJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->backup_job_id,
            'job_uuid' => $this->job_uuid,
            'backup_type' => $this->backup_type,
            'status' => $this->status,
            'stage' => $this->stage,
            'file_name' => $this->file_name,
            'file_size_bytes' => $this->file_size_bytes,
            'archive_sha256' => $this->archive_sha256,
            'drive_file_id' => $this->drive_file_id,
            'drive_web_view_link' => $this->drive_web_view_link,
            'drive_folder_id' => $this->drive_folder_id,
            'manifest_summary' => $this->manifest_summary,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at ? Carbon::parse($this->started_at)->toDateTimeString() : null,
            'finished_at' => $this->finished_at ? Carbon::parse($this->finished_at)->toDateTimeString() : null,
            'duration_ms' => $this->duration_ms,
            'creator_id' => $this->created_by,
            'creator_name' => optional($this->creator)->user_full_name,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
        ];
    }
}
