<?php

namespace App\Http\Resources\API\V1\Reporting;

use App\Services\Reporting\ReportRunData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReportRunData
 */
class ReportRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'report_run_id' => $this->reportRunId,
            'report_run_uuid' => $this->reportRunUuid,
            'scope_type' => $this->scopeType,
            'network_id' => $this->networkId,
            'report_key' => $this->reportKey,
            'report_title' => $this->reportTitle,
            'definition_version' => $this->definitionVersion,
            'status' => $this->status,
            'filters' => $this->filters,
            'artifact' => $this->artifact,
            'result_metadata' => $this->resultMetadata,
            'error' => $this->error,
            'queued_at' => $this->queuedAt,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
        ];
    }
}
