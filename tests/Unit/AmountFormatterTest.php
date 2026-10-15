<?php

namespace Tests\Unit;

use App\Helpers\AmountFormatter;
use PHPUnit\Framework\TestCase;

class AmountFormatterTest extends TestCase
{
    public function test_format_integers_with_thousands_separator(): void
    {
        $this->assertEquals('1,000.00', AmountFormatter::format(1000));
        $this->assertEquals('3,000.00', AmountFormatter::format(3000));
        $this->assertEquals('1,000,000.00', AmountFormatter::format(1000000));
        $this->assertEquals('50.00', AmountFormatter::format(50));
        $this->assertEquals('0.00', AmountFormatter::format(0));
        $this->assertEquals('0', AmountFormatter::format(null));
        $this->assertEquals('0', AmountFormatter::format(''));
    }

    public function test_format_decimals_and_trim_zeros(): void
    {
        $this->assertEquals('1,000', AmountFormatter::format(1000.00, 2, true));
        $this->assertEquals('1,250.5', AmountFormatter::format(1250.50, 2, true));
        $this->assertEquals('1,250.75', AmountFormatter::format(1250.75));
        $this->assertEquals('1,250.50', AmountFormatter::format(1250.50, 2, false));
    }

    public function test_format_negative_amounts(): void
    {
        $this->assertEquals('-1,500.00', AmountFormatter::format(-1500));
        $this->assertEquals('-1,500.00YER', AmountFormatter::formatWithCurrency(-1500, 'YER'));
    }

    public function test_format_with_currency_without_space(): void
    {
        $this->assertEquals('1,000.00YER', AmountFormatter::formatWithCurrency(1000, 'YER'));
        $this->assertEquals('3,000.00YER', AmountFormatter::formatWithCurrency(3000, 'YER'));
        $this->assertEquals('1,250.50YER', AmountFormatter::formatWithCurrency(1250.5, 'YER'));
        $this->assertEquals('50,000.00YER', AmountFormatter::formatWithCurrency('50000', 'YER'));
    }

    public function test_format_with_currency_with_space(): void
    {
        $this->assertEquals('1,000.00 YER', AmountFormatter::formatWithCurrency(1000, 'YER', 2, true));
        $this->assertEquals('3,000.00 USD', AmountFormatter::formatWithCurrency(3000, 'USD', 2, true));
    }

    public function test_global_helper_functions(): void
    {
        $this->assertEquals('1,000.00', formatAmount(1000));
        $this->assertEquals('1,000.00YER', formatWithCurrency(1000));
        $this->assertEquals('3,000.00YER', formatWithCurrency(3000));
    }
}
