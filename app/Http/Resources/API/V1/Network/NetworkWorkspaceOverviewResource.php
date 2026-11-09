<?php

namespace App\Http\Resources\API\V1\Network;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string, mixed> $resource
 */
class NetworkWorkspaceOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'generated_at' => $this->resource['generated_at'],
            'timezone' => $this->resource['timezone'],
            'period' => $this->resource['period'],
            'network' => (new NetworkResource($this->resource['network']))->resolve($request),
            'metrics' => $this->resource['metrics'],
            'recent_pending_requests' => $this->resource['recent_pending_requests'],
        ];
    }
}
