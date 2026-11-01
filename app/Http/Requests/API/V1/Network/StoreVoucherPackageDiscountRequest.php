<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoucherPackageDiscountRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network_id' => [
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('voucher_packages', 'package_id')->whereNull('deleted_at'),
            ],
            'pos_id' => [
                'required',
                'integer',
                Rule::exists('pos', 'pos_id')->whereNull('deleted_at'),
                Rule::unique('voucher_package_discounts', 'pos_id')
                    ->where('network_id', $this->network_id)
                    ->where('package_id', $this->package_id)
                    ->whereNull('deleted_at'),
            ],
            'discount_cash' => [
                'required',
                'numeric',
                'between:0,100',
            ],
            'discount_credit' => [
                'required',
                'numeric',
                'between:0,100',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
            'discount_start_date' => [
                'nullable',
                'date',
            ],
            'discount_end_date' => [
                'nullable',
                'date',
            ],
        ];
    }

    public static function parseDateBoundary(mixed $value, bool $isEndDate): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->timezone('Asia/Aden');
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // فحص ما إذا كانت القيمة تاريخاً مجرداً بصيغة YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            $parsed = Carbon::parse($str, 'Asia/Aden');

            if ($isEndDate) {
                // تاريخ الانتهاء المجرد يشمل اليوم كاملاً حتى نهايته (بداية اليوم التالي كحد استبعادي)
                return $parsed->addDay()->startOfDay();
            }

            return $parsed->startOfDay();
        }

        return Carbon::parse($str, 'Asia/Aden');
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('discount_start_date') || $validator->errors()->has('discount_end_date')) {
                return;
            }

            try {
                $startDate = $this->getDiscountStartDate();
                $endDate = $this->getDiscountEndDate();

                if ($startDate && $endDate && $endDate->lte($startDate)) {
                    $validator->errors()->add(
                        'discount_end_date',
                        'تاريخ نهاية الخصم يجب أن يكون لاحقاً لتاريخ البداية.'
                    );
                }
            } catch (\Throwable) {
                // Let the 'date' rule handle any parsing failures
                return;
            }
        });
    }

    public function getNetworkId(): int
    {
        return (int) $this->network_id;
    }

    public function getPackageId(): int
    {
        return (int) $this->package_id;
    }

    public function getPosId(): int
    {
        return (int) $this->pos_id;
    }

    public function getDiscountCash(): float
    {
        return (float) $this->discount_cash;
    }

    public function getDiscountCredit(): float
    {
        return (float) $this->discount_credit;
    }

    public function getStatus(): bool
    {
        return $this->has('status') ? (bool) $this->status : true;
    }

    public function getDiscountStartDate(): ?Carbon
    {
        if (! $this->has('discount_start_date')) {
            // غير ممرر عند الإنشاء -> افتراضي الوقت الحالي بتوقيت عدن
            return now('Asia/Aden');
        }

        $val = $this->input('discount_start_date');
        if ($val === null || $val === '') {
            // ممرر صراحة بقيمة null -> سريان فوري بدون حد أدنى
            return null;
        }

        return self::parseDateBoundary($val, false);
    }

    public function getDiscountEndDate(): ?Carbon
    {
        if (! $this->has('discount_end_date')) {
            // غير ممرر -> مفتوح الصلاحية بدون انتهاء
            return null;
        }

        $val = $this->input('discount_end_date');
        if ($val === null || $val === '') {
            return null;
        }

        return self::parseDateBoundary($val, true);
    }
}
