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
use Illuminate\Validation\ValidationException;

class NetworkWallet extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'network_wallets';

    protected $primaryKey = 'wallet_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'network_id',
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

    public function logs(): HasMany
    {
        return $this->hasMany(NetworkWalletLog::class, 'wallet_id', 'wallet_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    // Checkers
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function ensureActive(): void
    {
        if (! $this->isActive()) {
            throw ValidationException::withMessages([
                'network_wallet' => ['محفظة مزود الشبكة مجمدة حالياً، لا يمكن تنفيذ العمليات المالية عليها.'],
            ]);
        }
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return (float) $this->wallet_balance >= $amount;
    }

    // Operations
    public function deposit(float $amount, string $statement, int $userId): NetworkWalletLog
    {
        return DB::transaction(function () use ($amount, $statement, $userId): NetworkWalletLog {
            $this->increment('wallet_balance', $amount);

            return $this->logs()->create([
                'log_operation' => WalletOperationType::DEPOSIT,
                'log_amount' => $amount,
                'log_statement' => $statement,
                'log_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);
        });
    }

    public function withdraw(float $amount, string $statement, int $userId): NetworkWalletLog
    {
        return DB::transaction(function () use ($amount, $statement, $userId): NetworkWalletLog {
            $this->decrement('wallet_balance', $amount);

            return $this->logs()->create([
                'log_operation' => WalletOperationType::WITHDRAW,
                'log_amount' => $amount,
                'log_statement' => $statement,
                'log_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);
        });
    }
}
