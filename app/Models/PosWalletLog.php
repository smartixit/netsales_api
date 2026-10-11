<?php

namespace App\Models;

use App\Enums\WalletOperationType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosWalletLog extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'pos_wallet_logs';

    protected $primaryKey = 'log_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'wallet_id',
        'log_operation_type',
        'log_amount',
        'log_statement',
        'log_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'datetime',
            'log_amount' => 'decimal:2',
            'log_operation_type' => WalletOperationType::class,
        ];
    }

    // Relationships
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PosWallet::class, 'wallet_id', 'wallet_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
