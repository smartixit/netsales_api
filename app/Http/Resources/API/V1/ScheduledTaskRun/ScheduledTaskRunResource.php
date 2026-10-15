<?php

namespace App\Http\Resources\API\V1\ScheduledTaskRun;

use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Models\ScheduledTaskRun;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد تنسيق بيانات تشغيل المهام المجدولة (Scheduled Task Run Resource).
 *
 * @mixin ScheduledTaskRun
 */
class ScheduledTaskRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_type' => $this->task_type instanceof ScheduledTaskType ? $this->task_type->value : $this->task_type,
            'task_type_label' => $this->task_type instanceof ScheduledTaskType ? $this->task_type->label() : (ScheduledTaskType::tryFrom($this->task_type)?->label()),
            'network_id' => $this->network_id,
            'network_name' => $this->network?->network_name,
            'target_date' => $this->target_date ? Carbon::parse($this->target_date)->toDateString() : null,
            'status' => $this->status instanceof ScheduledTaskStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof ScheduledTaskStatus ? $this->status->label() : (ScheduledTaskStatus::tryFrom((string) $this->status)?->label()),
            'extra' => $this->extra,
            'sent_at' => $this->sent_at ? Carbon::parse($this->sent_at)->toDateTimeString() : null, 
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null, 
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
        ];
    }
}
