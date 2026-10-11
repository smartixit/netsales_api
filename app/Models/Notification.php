<?php

namespace App\Models;

use App\Enums\NotificationType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'notifications';

    protected $primaryKey = 'notification_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'notification_type',
        'notification_title',
        'notification_message',
        'notification_image',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
