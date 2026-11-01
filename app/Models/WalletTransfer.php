<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WalletTransfer extends Model
{
    protected $table = 'wallet_transfers';

    protected $primaryKey = 'transfer_id';

    protected $attributes = [
        'status' => 'COMPLETED',
    ];

    protected $fillable = [
        'transfer_uuid',
        'network_wallet_id',
        'pos_wallet_id',
        'amount',
        'direction',
        'original_transfer_id',
        'status',
        'statement',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'network_wallet_id' => 'integer',
            'pos_wallet_id' => 'integer',
            'amount' => 'decimal:2',
            'original_transfer_id' => 'integer',
            'is_reversal' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    public function networkWallet(): BelongsTo
    {
        return $this->belongsTo(NetworkWallet::class, 'network_wallet_id', 'wallet_id');
    }

    public function posWallet(): BelongsTo
    {
        return $this->belongsTo(PosWallet::class, 'pos_wallet_id', 'wallet_id');
    }

    public function originalTransfer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_transfer_id', 'transfer_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'original_transfer_id', 'transfer_id');
    }

    public function networkLogs(): HasMany
    {
        return $this->hasMany(NetworkWalletLog::class, 'transfer_id', 'transfer_id');
    }

    public function posLogs(): HasMany
    {
        return $this->hasMany(PosWalletLog::class, 'transfer_id', 'transfer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function isReversed(): bool
    {
        return $this->status === 'REVERSED';
    }

    public function isReversal(): bool
    {
        return (bool) $this->is_reversal;
    }
}
