<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class NetworkPos extends Model
{
    use ExactDateSerialization;

    protected $table = 'network_pos';

    protected $primaryKey = 'network_pos_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'pos_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    public function scopeForPos(Builder $query, int $posId): Builder
    {
        return $query->where('pos_id', $posId);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function ensureActive(): void
    {
        if (! $this->isActive()) {
            throw ValidationException::withMessages([
                'network_pos' => ['ارتباط نقطة البيع بالشبكة معطل حالياً.'],
            ]);
        }
    }
}
