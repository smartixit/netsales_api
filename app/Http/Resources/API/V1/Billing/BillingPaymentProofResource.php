<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Services\Support\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingPaymentProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_proof_id' => $this->payment_proof_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'uploaded_at' => $this->uploaded_at?->toDateTimeString(),
            'download_url' => app(FileUploadService::class)->temporarySignedUrl($this->file_path),
        ];
    }
}
