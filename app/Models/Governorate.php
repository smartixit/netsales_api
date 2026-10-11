<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
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

    protected $incrementing = true;

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
}
