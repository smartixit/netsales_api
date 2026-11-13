<?php

namespace App\Models;

use App\Enums\WalletOperationType;
use App\Services\Support\BusinessIdentifierGenerator;
use App\Support\DecimalAmount;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

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
        'opening_balance',
        'opening_balance_source',
        'currency_code',
        'credit_limit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
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

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }

    public function scopeForPos(Builder $query, int $posId): Builder
    {
        return $query->where('pos_id', $posId);
    }

    public function scopeForNetworkAndPos(Builder $query, int $networkId, int $posId): Builder
    {
        return $query->where('network_id', $networkId)->where('pos_id', $posId);
    }

    // Checkers & Accounting
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function ensureActive(): void
    {
        if (! $this->isActive()) {
            throw ValidationException::withMessages([
                'pos_wallet' => ['محفظة نقطة البيع مجمدة أو معطلة حالياً، لا يمكن تنفيذ العمليات المالية عليها.'],
            ]);
        }
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return DecimalAmount::toMinorUnits($this->wallet_balance)
            >= DecimalAmount::toMinorUnits($amount);
    }

    /**
     * هل المحفظة مكشوفة برصيد سالب (مدين على الوكيل لصالح الشبكة).
     */
    public function isOverdrawn(): bool
    {
        return DecimalAmount::toMinorUnits($this->wallet_balance) < 0;
    }

    /**
     * تحديد المركز المحاسبي لرصيد المحفظة وفق الأصول المحاسبية (مدين / دائن / متزن).
     *
     * @return 'DEBIT'|'CREDIT'|'ZERO'
     */
    public function getAccountingStatus(): string
    {
        $balance = DecimalAmount::toMinorUnits($this->wallet_balance);
        if ($balance < 0) {
            return 'DEBIT';
        }

        return $balance > 0 ? 'CREDIT' : 'ZERO';
    }

    /**
     * البيان المحاسبي لحالة المحفظة.
     */
    public function getAccountingStatusLabel(): string
    {
        return match ($this->getAccountingStatus()) {
            'DEBIT' => 'مدين (عليه مديونية للشبكة)',
            'CREDIT' => 'دائن (له رصيد متاح)',
            'ZERO' => 'متزن (لا يوجد رصيد أو مديونية)',
        };
    }

    /**
     * إجمالي مبلغ المديونية إن كانت المحفظة بالسالب (رصيد مدين).
     */
    public function getDebitAmount(): float
    {
        $balance = DecimalAmount::toMinorUnits($this->wallet_balance);

        return $balance < 0 ? (float) DecimalAmount::fromMinorUnits(abs($balance)) : 0.0;
    }

    /**
     * إجمالي الرصيد الدائن المتاح (رصيد دائن).
     */
    public function getCreditAmount(): float
    {
        $balance = DecimalAmount::toMinorUnits($this->wallet_balance);

        return $balance > 0 ? (float) DecimalAmount::fromMinorUnits($balance) : 0.0;
    }

    /**
     * هل تم منح سقف ائتماني معتمد لمحفظة نقطة البيع؟
     */
    public function hasCreditLimit(): bool
    {
        return $this->credit_limit !== null;
    }

    /**
     * السقف الائتماني المعتمد الممنوح للمحفظة.
     */
    public function getCreditLimit(): ?float
    {
        return $this->credit_limit !== null ? (float) $this->credit_limit : null;
    }

    /**
     * القدرة الائتمانية المتبقية للسحب على المكشوف.
     */
    public function getAvailableOverdraftCapacity(): float
    {
        if ($this->credit_limit === null) {
            return 0.0;
        }

        $limitMinor = DecimalAmount::toMinorUnits($this->credit_limit);
        $balanceMinor = DecimalAmount::toMinorUnits($this->wallet_balance);
        $usedOverdraftMinor = max(0, -$balanceMinor);
        $remainingMinor = max(0, $limitMinor - $usedOverdraftMinor);

        return (float) DecimalAmount::fromMinorUnits($remainingMinor);
    }

    /**
     * إجمالي القدرة الشرائية المتاحة المعتمدة رسمياً (الرصيد النقدي + السقف الائتماني المتاح).
     */
    public function getTotalPurchasingPower(): float
    {
        $balanceMinor = DecimalAmount::toMinorUnits($this->wallet_balance);
        $cashMinor = max(0, $balanceMinor);
        $limitMinor = DecimalAmount::toMinorUnits($this->credit_limit ?? 0);
        $usedOverdraftMinor = max(0, -$balanceMinor);
        $remainingCreditMinor = max(0, $limitMinor - $usedOverdraftMinor);

        return (float) DecimalAmount::fromMinorUnits(
            DecimalAmount::add($cashMinor, $remainingCreditMinor)
        );
    }

    // Operations
    public function deposit(float $amount, string $statement, int $userId): PosWalletLog
    {
        return DB::transaction(function () use ($amount, $statement, $userId): PosWalletLog {
            $wallet = self::query()->lockForUpdate()->findOrFail($this->getKey());
            $wallet->ensureActive();
            $amountMinor = DecimalAmount::toMinorUnits($amount);
            if ($amountMinor <= 0) {
                throw ValidationException::withMessages(['amount' => ['مبلغ الإيداع يجب أن يكون أكبر من الصفر.']]);
            }
            $currencyCode = strtoupper(trim((string) $wallet->currency_code));
            if (preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1) {
                throw new LogicException('The POS wallet currency code is invalid.');
            }
            $beforeMinor = DecimalAmount::toMinorUnits($wallet->wallet_balance);
            $afterMinor = DecimalAmount::add($beforeMinor, $amountMinor);
            $wallet->wallet_balance = DecimalAmount::fromMinorUnits($afterMinor);
            $wallet->save();
            return $wallet->logs()->create([
                'log_operation_type' => WalletOperationType::DEPOSIT,
                'log_amount' => DecimalAmount::fromMinorUnits($amountMinor),
                'balance_before' => DecimalAmount::fromMinorUnits($beforeMinor),
                'balance_after' => DecimalAmount::fromMinorUnits($afterMinor),
                'balance_snapshot_source' => 'LIVE',
                'currency_code' => $currencyCode,
                'log_statement' => $statement,
                'log_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);
        }, 3);
    }

    public function withdraw(float $amount, string $statement, int $userId): PosWalletLog
    {
        return DB::transaction(function () use ($amount, $statement, $userId): PosWalletLog {
            $wallet = self::query()->lockForUpdate()->findOrFail($this->getKey());
            $wallet->ensureActive();
            $amountMinor = DecimalAmount::toMinorUnits($amount);
            $beforeMinor = DecimalAmount::toMinorUnits($wallet->wallet_balance);
            $creditLimitMinor = DecimalAmount::toMinorUnits($wallet->credit_limit ?? 0);
            $afterMinor = DecimalAmount::add($beforeMinor, -$amountMinor);
            if ($amountMinor <= 0 || $afterMinor < -$creditLimitMinor) {
                throw ValidationException::withMessages(['amount' => ['تجاوز السحب الرصيد المتاح والسقف الائتماني المعتمد لنقطة البيع.']]);
            }
            $currencyCode = strtoupper(trim((string) $wallet->currency_code));
            if (preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1) {
                throw new LogicException('The POS wallet currency code is invalid.');
            }
            $wallet->wallet_balance = DecimalAmount::fromMinorUnits($afterMinor);
            $wallet->save();
            return $wallet->logs()->create([
                'log_operation_type' => WalletOperationType::WITHDRAW,
                'log_amount' => DecimalAmount::fromMinorUnits($amountMinor),
                'balance_before' => DecimalAmount::fromMinorUnits($beforeMinor),
                'balance_after' => DecimalAmount::fromMinorUnits($afterMinor),
                'balance_snapshot_source' => 'LIVE',
                'currency_code' => $currencyCode,
                'log_statement' => $statement,
                'log_date' => now('Asia/Aden'),
                'created_by' => $userId,
            ]);
        }, 3);
    }
}
