<?php

namespace App\Models;

use App\Enums\BankAccountType;
use App\Services\Support\FileUploadService;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class BankAccount extends Model
{
    use ExactDateSerialization;
    use SoftDeletes;

    protected $table = 'bank_accounts';

    protected $primaryKey = 'account_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'bank_name',
        'bank_logo',
        'account_holder_name',
        'account_number',
        'account_type',
        'account_currency',
        'account_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'account_balance' => 'decimal:2',
            'account_type' => BankAccountType::class,
            'status' => 'boolean',
        ];
    }

    // Relationships
    public function subscriptionPlanRequests(): HasMany
    {
        return $this->hasMany(SubscriptionPlanRequest::class, 'account_id', 'account_id');
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

    public function scopeByType(Builder $query, BankAccountType|string $type): Builder
    {
        $val = $type instanceof BankAccountType ? $type->value : $type;

        return $query->where('account_type', $val);
    }

    public function scopeByCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('account_currency', $currency);
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
                'account_id' => ["الحساب البنكي المحدد [{$this->bank_name} - {$this->account_number}] معطل حالياً."],
            ]);
        }
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return (float) $this->account_balance >= $amount;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->bank_logo)) {
            return null;
        }

        return app(FileUploadService::class)->url($this->bank_logo);
    }
}
