<?php

namespace Tests\Unit;

use App\Support\CsvValueSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CsvValueSanitizerTest extends TestCase
{
    #[DataProvider('formulaLikeValues')]
    public function test_formula_like_values_are_neutralized(string $value): void
    {
        $this->assertSame("'".$value, CsvValueSanitizer::sanitize($value));
    }

    /** @return array<string, array{string}> */
    public static function formulaLikeValues(): array
    {
        return [
            'equals prefix' => ['=1+1'],
            'plus prefix' => ['+1+1'],
            'minus prefix' => ['-SUM(A1:A2)'],
            'at prefix' => ['@SUM(A1:A2)'],
            'leading spaces' => ['   =1+1'],
            'leading tab' => ["\t=1+1"],
            'leading line breaks' => ["\r\n=1+1"],
            'leading control characters' => ["\x00\x1F@SUM(A1:A2)"],
        ];
    }

    public function test_regular_values_and_numeric_strings_remain_unchanged(): void
    {
        $this->assertSame('NetSales', CsvValueSanitizer::sanitize('NetSales'));
        $this->assertSame('123.45', CsvValueSanitizer::sanitize('123.45'));
        $this->assertSame('-123.45', CsvValueSanitizer::sanitize('-123.45'));
        $this->assertNull(CsvValueSanitizer::sanitize(null));
        $this->assertSame(123, CsvValueSanitizer::sanitize(123));
    }
}
