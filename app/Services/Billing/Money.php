<?php

namespace App\Services\Billing;

use InvalidArgumentException;
use OverflowException;

final readonly class Money
{
    public function __construct(
        public int $amountMinor,
        public string $currencyCode
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new InvalidArgumentException('يجب أن يكون رمز العملة بصيغة ISO 4217.');
        }
    }

    public static function fromDecimal(string $amount, int $decimalPlaces, string $currencyCode): self
    {
        if ($decimalPlaces < 0 || $decimalPlaces > 4) {
            throw new InvalidArgumentException('عدد المنازل العشرية للعملة خارج النطاق المدعوم.');
        }

        $value = trim($amount);
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('صيغة المبلغ غير صالحة.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > $decimalPlaces) {
            throw new InvalidArgumentException('المبلغ يحتوي على منازل عشرية أكثر مما تسمح به العملة.');
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $minorDigits = str_pad($fraction, $decimalPlaces, '0');
        $digits = ltrim($whole.$minorDigits, '0');
        $digits = $digits === '' ? '0' : $digits;
        $signedDigits = (($matches[1] ?? '') === '-' && $digits !== '0') ? '-'.$digits : $digits;
        $minor = filter_var($signedDigits, FILTER_VALIDATE_INT);

        if ($minor === false) {
            throw new OverflowException('المبلغ يتجاوز السعة الرقمية المسموحة.');
        }

        return new self((int) $minor, $currencyCode);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        if (($other->amountMinor > 0 && $this->amountMinor > PHP_INT_MAX - $other->amountMinor)
            || ($other->amountMinor < 0 && $this->amountMinor < PHP_INT_MIN - $other->amountMinor)) {
            throw new OverflowException('تجاوز مجموع المبالغ الحد الرقمي المسموح.');
        }

        return new self($this->amountMinor + $other->amountMinor, $this->currencyCode);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        if ($other->amountMinor === PHP_INT_MIN) {
            throw new OverflowException('تعذر طرح القيمة بسبب تجاوز السعة الرقمية.');
        }

        return $this->add(new self(-$other->amountMinor, $other->currencyCode));
    }

    public function applyBasisPoints(int $basisPoints): self
    {
        if ($basisPoints < 0 || $basisPoints > 100000) {
            throw new InvalidArgumentException('نسبة الأساس غير صالحة.');
        }

        $negative = $this->amountMinor < 0;
        $absolute = abs($this->amountMinor);
        $whole = intdiv($absolute, 10000);
        $remainder = $absolute % 10000;

        if ($basisPoints !== 0 && $whole > intdiv(PHP_INT_MAX, $basisPoints)) {
            throw new OverflowException('نتيجة النسبة تتجاوز السعة الرقمية المسموحة.');
        }

        $result = ($whole * $basisPoints) + intdiv(($remainder * $basisPoints) + 5000, 10000);
        if ($negative) {
            $result *= -1;
        }

        return new self($result, $this->currencyCode);
    }

    public function toDecimalString(int $decimalPlaces): string
    {
        if ($decimalPlaces < 0 || $decimalPlaces > 4) {
            throw new InvalidArgumentException('عدد المنازل العشرية للعملة غير صالح.');
        }

        $negative = $this->amountMinor < 0;
        $absolute = abs($this->amountMinor);
        if ($decimalPlaces === 0) {
            return ($negative ? '-' : '').(string) $absolute;
        }

        $factor = 10 ** $decimalPlaces;
        $whole = intdiv($absolute, $factor);
        $fraction = str_pad((string) ($absolute % $factor), $decimalPlaces, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currencyCode !== $other->currencyCode) {
            throw new InvalidArgumentException('لا يمكن جمع أو طرح مبالغ بعملات مختلفة دون تحويل موثق.');
        }
    }
}