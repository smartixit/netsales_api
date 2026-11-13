<?php

namespace App\Http\Resources\API\V1\Documents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OperationalDocumentSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'snapshot_uuid' => $this->snapshot_uuid,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'document_type' => $this->document_type,
            'document_id' => $this->document_id,
            'document_version' => $this->document_version,
            'document_reference' => $this->document_reference,
            'template_id' => $this->template_id,
            'template_version' => $this->template_version,
            'schema_version' => $this->schema_version,
            'currency_code' => $this->currency_code,
            'snapshot_sha256' => $this->snapshot_sha256,
            'source_snapshot' => $this->source_snapshot,
            'artifact_sha256' => $this->artifact_sha256,
            'artifact_size' => $this->artifact_size,
            'artifact_ready' => $this->artifact_path !== null,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'created_by' => $this->created_by,
        ];
    }
}
