<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkPosAgreement extends Model
{
    use ExactDateSerialization;

    protected $table = 'network_pos_agreements';

    protected $primaryKey = 'agreement_id';

    protected $fillable = [
        'network_pos_id', 'version', 'title', 'reference', 'terms',
        'starts_at', 'ends_at', 'status', 'created_by', 'activated_by',
        'activated_at', 'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function resolveValidityState(): string
    {
        if ($this->status === 'DRAFT') return 'DRAFT';
        if ($this->status === 'TERMINATED') return 'TERMINATED';
        if ($this->status === 'EXPIRED') return 'EXPIRED';
        if ($this->starts_at && $this->starts_at->isFuture()) return 'SCHEDULED';
        if ($this->ends_at && $this->ends_at->isPast()) return 'EXPIRED';
        return 'EFFECTIVE';
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(NetworkPos::class, 'network_pos_id', 'network_pos_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by', 'user_id');
    }
}
