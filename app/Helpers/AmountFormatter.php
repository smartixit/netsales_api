<?php

namespace App\Helpers;

/**
 * فئة مساعدة لتنسيق المبالغ والأرقام المالية بدقة وكود نظيف (Clean Amount Formatter).
 * مصممة لتكون مستقلة تماماً وقابلة للاستخدام وإعادة التدوير في أي مشروع PHP أو Laravel.
 */
class AmountFormatter
{
    /**
     * تنسيق الأرقام بفاصلة الآلاف مع التحكم بعدد الخانات العشرية وتجريد الأصفار غير اللازمة.
     *
     * @param  float|int|string|null  $amount  المبلغ المراد تنسيقه
     * @param  int  $maxDecimals  الحد الأقصى للمنازل العشرية (افتراضياً 2)
     * @param  bool  $trimZeroDecimals  تجريد الأصفار الزائدة بعد الفاصلة (.00 تصبح بدون كسور)
     * @param  string  $decPoint  رمز الفاصلة العشرية (افتراضياً '.')
     * @param  string  $thousandsSep  فاصلة الآلاف (افتراضياً ',')
     */
    public static function format(
        float|int|string|null $amount,
        int $maxDecimals = 2,
        bool $trimZeroDecimals = false,
        string $decPoint = '.',
        string $thousandsSep = ','
    ): string {
        if ($amount === null || $amount === '') {
            return '0';
        }

        $numeric = (float) $amount;
        $formatted = number_format($numeric, $maxDecimals, $decPoint, $thousandsSep);

        if ($trimZeroDecimals && str_contains($formatted, $decPoint)) {
            $formatted = rtrim(rtrim($formatted, '0'), $decPoint);
        }

        return $formatted;
    }

    /**
     * تنسيق المبلغ مع لاحقة العملة (مثل: 1,000YER أو 1,000 YER).
     *
     * @param  float|int|string|null  $amount  المبلغ
     * @param  string  $currency  رمز أو اسم العملة (افتراضياً YER)
     * @param  int  $maxDecimals  الحد الأقصى للمنازل العشرية
     * @param  bool  $space  إضافة مسافة بين المبلغ والعملة أم لا (افتراضياً false للالتزام بنمط 1,000YER)
     * @param  bool  $trimZeroDecimals  تجريد الأصفار العشرية الزائدة
     */
    public static function formatWithCurrency(
        float|int|string|null $amount,
        string $currency = 'YER',
        int $maxDecimals = 2,
        bool $space = false,
        bool $trimZeroDecimals = false
    ): string {
        $formatted = self::format($amount, $maxDecimals, $trimZeroDecimals);
        $spacing = $space ? ' ' : '';

        return $formatted.$spacing.$currency;
    }
}
