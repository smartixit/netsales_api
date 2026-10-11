<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActionType extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'action_types';

    protected $primaryKey = 'action_type_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'action_type_key',
        'action_type_name',
        'action_type_desc',
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
        return $this->hasMany(Permission::class, 'action_type_id', 'action_type_id');
    }
}
