<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkPosEvent extends Model
{
    public $timestamps = false;

    protected $table = 'network_pos_events';

    protected $primaryKey = 'event_id';

    protected $fillable = ['network_pos_id', 'actor_user_id', 'event_type', 'payload', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(NetworkPos::class, 'network_pos_id', 'network_pos_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'user_id');
    }
}
