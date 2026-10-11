<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'requests';

    protected $primaryKey = 'request_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'pos_id',
        'request_uuid',
        'request_total_amount',
        'request_date',
        'status',
        'created_by',
        'reviewed_by',
        'review_date',
        'request_note',
    ];

    protected function casts(): array
    {
        return [
            'request_total_amount' => 'decimal:2',
            'request_date' => 'datetime',
            'status' => RequestStatus::class,
            'review_date' => 'datetime',
        ];
    }

    // Auto generate request_uuid
    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (empty($request->request_uuid)) {
                $request->request_uuid = BusinessIdentifierGenerator::forRequest($request->request_id);
            }
        });
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'request_id', 'request_id');
    }
}
