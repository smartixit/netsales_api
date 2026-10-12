<?php

namespace App\Models;

use App\Enums\WalletOperationType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkWalletLog extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'network_wallet_logs';

    protected $primaryKey = 'log_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'wallet_id',
        'log_operation',
        'log_amount',
        'log_statement',
        'log_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'datetime',
            'log_operation' => WalletOperationType::class,
            'log_amount' => 'decimal:2',
        ];
    }

    // Relationships
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(NetworkWallet::class, 'wallet_id', 'wallet_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    // Scopes
    public function scopeDeposits(Builder $query): Builder
    {
        return $query->where('log_operation', WalletOperationType::DEPOSIT);
    }

    public function scopeWithdrawals(Builder $query): Builder
    {
        return $query->where('log_operation', WalletOperationType::WITHDRAW);
    }

    public function scopeForWallet(Builder $query, int $walletId): Builder
    {
        return $query->where('wallet_id', $walletId);
    }

    // Checkers
    public function isDeposit(): bool
    {
        return $this->log_operation === WalletOperationType::DEPOSIT;
    }

    public function isWithdrawal(): bool
    {
        return $this->log_operation === WalletOperationType::WITHDRAW;
    }
}
