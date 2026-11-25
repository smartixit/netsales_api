<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class NetworkPosEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Network POS lifecycle events are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Network POS lifecycle events cannot be deleted.');
        });
    }

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
