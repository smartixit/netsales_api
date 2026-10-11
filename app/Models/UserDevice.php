<?php

namespace App\Models;

use App\Enums\DeviceType;
use App\Enums\UserDeviceStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDevice extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'user_devices';

    protected $primaryKey = 'device_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'device_uuid',
        'device_name',
        'device_type',
        'is_primary',
        'status',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'last_used_at' => 'datetime',
            'device_type' => DeviceType::class,
            'status' => UserDeviceStatus::class,
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function securityBans(): HasMany
    {
        return $this->hasMany(SecurityBan::class, 'device_id', 'device_id');
    }
}
