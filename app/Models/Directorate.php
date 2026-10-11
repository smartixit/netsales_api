<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
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
}
