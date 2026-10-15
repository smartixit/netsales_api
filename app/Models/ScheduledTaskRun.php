<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTaskRun extends Model
{
    use ExactDateSerialization;

    protected $table = 'scheduled_task_runs';

    protected $fillable = [
        'task_type',
        'network_id',
        'target_date',
        'status',
        'extra',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'target_date' => 'date:Y-m-d',
            'extra' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }
}
