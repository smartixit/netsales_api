<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Governorate extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'governorates';

    protected $primaryKey = 'governorate_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'governorate_name',
    ];

    protected function casts(): array
    {
        return [

        ];
    }

    // Relationships
    public function directorates(): HasMany
    {
        return $this->hasMany(Directorate::class, 'governorate_id', 'governorate_id');
    }

    public function networks(): HasMany
    {
        return $this->hasMany(Network::class, 'governorate_id', 'governorate_id');
    }

    public function pos(): HasMany
    {
        return $this->hasMany(Pos::class, 'governorate_id', 'governorate_id');
    }

    // Scopes
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('governorate_name', 'like', "%{$term}%");
    }

    public function scopeWithDirectoratesCount(Builder $query): Builder
    {
        return $query->withCount('directorates');
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
    public function directoratesCount(): int
    {
        return (int) ($this->directorates_count ?? $this->directorates()->count());
    }

    public function networksCount(): int
    {
        return (int) ($this->networks_count ?? $this->networks()->count());
    }

    public function posCount(): int
    {
        return (int) ($this->pos_count ?? $this->pos()->count());
    }
}
