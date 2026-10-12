<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoucherPackageRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'package_name' => is_string($this->package_name) ? trim($this->package_name) : $this->package_name,
        ]);
    }

    public function rules(): array
    {
        return [
            'network_id' => [
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'package_name' => [
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
                'required',
                'numeric',
                'min:0',
            ],
            'package_default_cash_discount' => [
                'required',
                'numeric',
                'between:0,100',
            ],
            'package_default_credit_discount' => [
                'required',
                'numeric',
                'between:0,100',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
