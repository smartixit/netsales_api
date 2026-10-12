<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherPackageRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->package_name)) {
            $this->merge(['package_name' => trim($this->package_name)]);
        }
    }

    public function rules(): array
    {
        return [
            'package_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'package_color' => [
                'nullable',
                'string',
                'max:20',
            ],
            'package_balance' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'package_uptime' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'package_validity' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'package_voucher_count' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'package_voucher_price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'package_default_cash_discount' => [
                'sometimes',
                'required',
                'numeric',
                'between:0,100',
            ],
            'package_default_credit_discount' => [
                'sometimes',
                'required',
                'numeric',
                'between:0,100',
            ],
            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
