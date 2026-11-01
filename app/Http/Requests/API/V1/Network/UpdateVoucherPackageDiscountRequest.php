<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use App\Models\VoucherPackageDiscount;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherPackageDiscountRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_cash' => [
                'sometimes',
                'required',
                'numeric',
                'between:0,100',
            ],
            'discount_credit' => [
                'sometimes',
                'required',
                'numeric',
                'between:0,100',
            ],
            'status' => [
                'sometimes',
                'boolean',
            ],
            'discount_start_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'discount_end_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('discount_start_date') || $validator->errors()->has('discount_end_date')) {
                return;
            }

            try {
                $id = $this->route('id') ?? $this->route('voucher_package_discount');
                $existing = $id ? VoucherPackageDiscount::find($id) : null;

                $startDate = $this->has('discount_start_date')
                    ? StoreVoucherPackageDiscountRequest::parseDateBoundary($this->input('discount_start_date'), false)
                    : $existing?->discount_start_date;

                $endDate = $this->has('discount_end_date')
                    ? StoreVoucherPackageDiscountRequest::parseDateBoundary($this->input('discount_end_date'), true)
                    : $existing?->discount_end_date;

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
}
