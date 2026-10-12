<?php

namespace App\Models;

use App\Enums\WalletOperationType;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PosWallet extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'pos_wallets';

    protected $primaryKey = 'wallet_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
        'pos_id',
        'wallet_uuid',
        'wallet_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    // Auto generate wallet_uuid
    protected static function booted(): void
    {
        static::creating(function (self $wallet): void {
            if (empty($wallet->wallet_uuid)) {
                $wallet->wallet_uuid = BusinessIdentifierGenerator::forWallet(self::class, $wallet->wallet_id);
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

    public function logs(): HasMany
    {
        return $this->hasMany(PosWalletLog::class, 'wallet_id', 'wallet_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeForNetworkAndPos(Builder $query, int $networkId, int $posId): Builder
    {
        return $query->where('network_id', $networkId)->where('pos_id', $posId);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return (float) $this->wallet_balance >= $amount;
    }

    // Operations
    public function deposit(float $amount, string $statement, int $userId): PosWalletLog
    {
        return DB::transaction(function () use ($amount, $statement, $userId): PosWalletLog {
            $this->increment('wallet_balance', $amount);

            return $this->logs()->create([
                'log_operation_type' => WalletOperationType::DEPOSIT,
                'log_amount' => $amount,
                'log_statement' => $statement,
                'log_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);
        });
    }

    public function withdraw(float $amount, string $statement, int $userId): PosWalletLog
    {
        return DB::transaction(function () use ($amount, $statement, $userId): PosWalletLog {
            $this->decrement('wallet_balance', $amount);

            return $this->logs()->create([
                'log_operation_type' => WalletOperationType::WITHDRAW,
                'log_amount' => $amount,
                'log_statement' => $statement,
                'log_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);
        });
    }
}
