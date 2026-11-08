<?php

namespace App\Http\Resources\API\V1\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOverviewResource extends JsonResource
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
            'metrics' => $this->resource['metrics'],
            'charts' => $this->resource['charts'],
            'attention' => $this->resource['attention'],
            'recent_activity' => $this->resource['recent_activity'],
        ];
    }
}
