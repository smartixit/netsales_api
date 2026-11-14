<?php

namespace App\Http\Resources\API\V1\Reporting;

use App\Models\ReportRun;
use App\Services\Reporting\ReportDefinitionRegistry;
use App\Services\Support\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReportRun
 */
class ReportRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $definition = app(ReportDefinitionRegistry::class)
            ->get((string) $this->scope_type, (string) $this->report_key);
        $artifactAvailable = $this->status === 'COMPLETED'
            && $this->artifact_path !== null
            && ($this->expires_at === null || ! $this->expires_at->isPast());

        return [
            'report_run_id' => (int) $this->report_run_id,
            'report_run_uuid' => (string) $this->report_run_uuid,
            'scope_type' => $this->scope_type,
            'network_id' => $this->network_id,
            'report_key' => $this->report_key,
            'report_title' => $definition['title'],
            'definition_version' => $this->definition_version,
            'status' => $this->status,
            'filters' => $this->filters ?? [],
            'artifact' => $artifactAvailable ? [
                'format' => $this->artifact_format,
                'bytes' => (int) $this->artifact_bytes,
                'sha256' => $this->artifact_sha256,
                'records_count' => (int) $this->records_count,
                'is_truncated' => (bool) $this->is_truncated,
                'expires_at' => $this->expires_at?->toDateTimeString(),
                'download_url' => app(FileUploadService::class)
                    ->temporarySignedUrl($this->artifact_path),
            ] : null,
            'result_metadata' => $this->result_metadata,
            'error' => $this->status === 'FAILED' ? [
                'code' => $this->error_code,
                'message' => $this->error_message,
            ] : null,
            'queued_at' => $this->queued_at?->toDateTimeString(),
            'started_at' => $this->started_at?->toDateTimeString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
        ];
    }
}
