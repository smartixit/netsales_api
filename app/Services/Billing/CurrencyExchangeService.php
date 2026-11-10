<?php

namespace App\Services\Billing;

use App\Models\{Currency, CurrencyConversion, CurrencyExchangeRate, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OverflowException;

class CurrencyExchangeService
{
    public function __construct(
        private CurrencyService $currencies,
        private BillingAuthorizationService $authorization
    ) {}

    public function recordRate(
        int $baseCurrencyId,
        int $quoteCurrencyId,
        int $numerator,
        int $denominator,
        string $source,
        User $actor,
        ?\DateTimeInterface $expiresAt = null
    ): CurrencyExchangeRate {
        $this->authorization->assertPlatform($actor);

        if ($baseCurrencyId === $quoteCurrencyId || $numerator <= 0 || $denominator <= 0 || trim($source) === '') {
            throw ValidationException::withMessages(['rate' => ['بيانات سعر الصرف غير صالحة.']]);
        }

        $this->currencies->getActive($baseCurrencyId);
        $this->currencies->getActive($quoteCurrencyId);

        return CurrencyExchangeRate::query()->create([
            'base_currency_id' => $baseCurrencyId,
            'quote_currency_id' => $quoteCurrencyId,
            'rate_numerator' => $numerator,
            'rate_denominator' => $denominator,
            'source' => trim($source),
            'effective_at' => now('Asia/Aden'),
            'expires_at' => $expiresAt,
            'recorded_by' => $actor->user_id,
        ]);
    }

    public function convert(
        int $rateId,
        int $sourceAmountMinor,
        int $feeMinor,
        string $sourceType,
        int $sourceId,
        User $actor
    ): CurrencyConversion {
        $this->authorization->assertPlatform($actor);

        if ($sourceAmountMinor <= 0 || $feeMinor < 0 || $feeMinor >= $sourceAmountMinor
            || trim($sourceType) === '' || $sourceId <= 0) {
            throw ValidationException::withMessages(['amount_minor' => ['مبلغ التحويل أو الرسوم غير صالح.']]);
        }

        return DB::transaction(function () use ($rateId, $sourceAmountMinor, $feeMinor, $sourceType, $sourceId, $actor): CurrencyConversion {
            $existing = CurrencyConversion::query()
                ->with(['fromCurrency', 'toCurrency', 'exchangeRate'])
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ((int) $existing->exchange_rate_id !== $rateId
                    || (int) $existing->source_amount_minor !== $sourceAmountMinor
                    || (int) $existing->fee_amount_minor !== $feeMinor) {
                    throw ValidationException::withMessages([
                        'source_id' => ['تم تسجيل تحويل سابق لهذه العملية المصدرية ببيانات مختلفة.'],
                    ]);
                }

                return $existing;
            }

            $rate = CurrencyExchangeRate::query()
                ->with(['baseCurrency', 'quoteCurrency'])
                ->lockForUpdate()
                ->findOrFail($rateId);

            if ($rate->effective_at->isFuture() || ($rate->expires_at && $rate->expires_at->isPast())) {
                throw ValidationException::withMessages(['exchange_rate_id' => ['سعر الصرف غير ساري في الوقت الحالي.']]);
            }

            $from = $rate->baseCurrency;
            $to = $rate->quoteCurrency;
            $netSourceAmount = $sourceAmountMinor - $feeMinor;
            $converted = $this->convertMinor(
                $netSourceAmount,
                (int) $rate->rate_numerator,
                (int) $rate->rate_denominator,
                (int) $from->decimal_places,
                (int) $to->decimal_places
            );

            return CurrencyConversion::query()->create([
                'exchange_rate_id' => $rate->exchange_rate_id,
                'from_currency_id' => $from->currency_id,
                'to_currency_id' => $to->currency_id,
                'source_amount_minor' => $sourceAmountMinor,
                'converted_amount_minor' => $converted,
                'fee_amount_minor' => $feeMinor,
                'rounding_mode' => 'HALF_UP',
                'rate_snapshot' => $this->decimalRate($rate),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'created_by' => $actor->user_id,
                'converted_at' => now('Asia/Aden'),
            ]);
        }, 3);
    }

    private function convertMinor(int $amount, int $numerator, int $denominator, int $fromDecimals, int $toDecimals): int
    {
        $numeratorFactors = [$amount, $numerator, 10 ** $toDecimals];
        $denominatorFactors = [$denominator, 10 ** $fromDecimals];

        foreach ($numeratorFactors as $i => $factor) {
            foreach ($denominatorFactors as $j => $divisor) {
                $gcd = $this->gcd($factor, $divisor);
                if ($gcd > 1) {
                    $numeratorFactors[$i] = intdiv($factor, $gcd);
                    $denominatorFactors[$j] = intdiv($divisor, $gcd);
                }
            }
        }

        $top = 1;
        foreach ($numeratorFactors as $factor) {
            if ($factor > 0 && $top > intdiv(PHP_INT_MAX, $factor)) {
                throw new OverflowException('عملية التحويل تتجاوز السعة الرقمية؛ قسّم المبلغ إلى تحويلات أصغر.');
            }
            $top *= $factor;
        }

        $bottom = 1;
        foreach ($denominatorFactors as $factor) {
            if ($factor > 0 && $bottom > intdiv(PHP_INT_MAX, $factor)) {
                throw new OverflowException('مقام سعر الصرف يتجاوز السعة الرقمية.');
            }
            $bottom *= $factor;
        }

        $quotient = intdiv($top, $bottom);
        $remainder = $top % $bottom;

        if ($remainder >= intdiv($bottom, 2) + ($bottom % 2)) {
            if ($quotient === PHP_INT_MAX) {
                throw new OverflowException('نتيجة التحويل تتجاوز السعة الرقمية.');
            }
            $quotient++;
        }

        return $quotient;
    }

    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, $a);
    }

    private function decimalRate(CurrencyExchangeRate $rate): string
    {
        return $rate->rate_numerator.'/'.$rate->rate_denominator;
    }
}
