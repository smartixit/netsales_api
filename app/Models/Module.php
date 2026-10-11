<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;
    protected $table = 'modules';

    protected $primaryKey = 'module_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'module_key',
        'module_name',
        'module_icon',
        'module_desc',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'module_id', 'module_id');
    }
}
