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
        'opening_balance',
        'opening_balance_source',
        'currency_code',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:2',
            'opening_balance' => 'decimal:2',
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
            $wallet = self::query()->lockForUpdate()->findOrFail($this->getKey());
            $wallet->ensureActive();
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['مبلغ الإيداع يجب أن يكون أكبر من الصفر.'],
                ]);
            }

            $before = round((float) $wallet->wallet_balance, 2);
            $wallet->wallet_balance = round($before + $amount, 2);
            $wallet->save();

            return $wallet->logs()->create([
                'log_operation' => WalletOperationType::DEPOSIT,
                'log_amount' => round($amount, 2),
                'balance_before' => $before,
                'balance_after' => (float) $wallet->wallet_balance,
                'balance_snapshot_source' => 'LIVE',
                'currency_code' => $wallet->currency_code ?: 'YER',
                'log_statement' => $statement,
                'log_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);
        }, 3);
    }

    public function withdraw(float $amount, string $statement, int $userId): NetworkWalletLog
    {
        return DB::transaction(function () use ($amount, $statement, $userId): NetworkWalletLog {
            $wallet = self::query()->lockForUpdate()->findOrFail($this->getKey());
            $wallet->ensureActive();
            $before = round((float) $wallet->wallet_balance, 2);
            if ($amount <= 0 || $before + 0.001 < $amount) {
                throw ValidationException::withMessages([
                    'amount' => ['رصيد محفظة الشبكة غير كافٍ لإتمام السحب.'],
                ]);
            }

            $wallet->wallet_balance = round($before - $amount, 2);
            $wallet->save();

            return $wallet->logs()->create([
                'log_operation' => WalletOperationType::WITHDRAW,
                'log_amount' => round($amount, 2),
                'balance_before' => $before,
                'balance_after' => (float) $wallet->wallet_balance,
                'balance_snapshot_source' => 'LIVE',
                'currency_code' => $wallet->currency_code ?: 'YER',
                'log_statement' => $statement,
                'log_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);
        }, 3);
    }
}
