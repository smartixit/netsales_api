<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class DecimalAmount
{
    public static function toMinorUnits(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $decimal = is_float($value)
            ? number_format($value, 2, '.', '')
            : trim((string) $value);

        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/D', $decimal, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid decimal amount.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new InvalidArgumentException('Amounts support at most two decimal places.');
        }

        $whole = ltrim($matches[2], '0') ?: '0';
        $maximumWhole = intdiv(PHP_INT_MAX - 99, 100);
        $maximumWholeString = (string) $maximumWhole;

        if (strlen($whole) > strlen($maximumWholeString)
            || (strlen($whole) === strlen($maximumWholeString)
                && strcmp($whole, $maximumWholeString) > 0)) {
            throw new OverflowException('The amount exceeds the supported integer range.');
        }

        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $minorUnits = ((int) $whole * 100) + (int) $fraction;
        $isZero = $minorUnits === 0;

        return ($matches[1] ?? '') === '-' && ! $isZero
            ? -$minorUnits
            : $minorUnits;
    }

    public static function fromMinorUnits(int $minorUnits): string
    {
        $negative = $minorUnits < 0;
        $absolute = abs($minorUnits);

        return ($negative ? '-' : '')
            .intdiv($absolute, 100)
            .'.'
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function add(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new OverflowException('The decimal amount total exceeds the supported integer range.');
        }

        return $left + $right;
    }

    public static function format(mixed $value): string
    {
        return self::fromMinorUnits(self::toMinorUnits($value));
    }
}
