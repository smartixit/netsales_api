<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Directorate extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'directorates';

    protected $primaryKey = 'directorate_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'directorate_name',
        'governorate_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }

    // Relationships
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'governorate_id', 'governorate_id');
    }

    public function networks(): HasMany
    {
        return $this->hasMany(Network::class, 'directorate_id', 'directorate_id');
    }

    public function pos(): HasMany
    {
        return $this->hasMany(Pos::class, 'directorate_id', 'directorate_id');
    }

    // Scopes
    public function scopeInGovernorate(Builder $query, int $governorateId): Builder
    {
        return $query->where('governorate_id', $governorateId);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('directorate_name', 'like', "%{$term}%");
    }

    public function scopeWithNetworksCount(Builder $query): Builder
    {
        return $query->withCount('networks');
    }

    public function scopeWithPosCount(Builder $query): Builder
    {
        return $query->withCount('pos');
    }

    // Helpers & Stats
    public function networksCount(): int
    {
        return (int) ($this->networks_count ?? $this->networks()->count());
    }

    public function posCount(): int
    {
        return (int) ($this->pos_count ?? $this->pos()->count());
    }
}
