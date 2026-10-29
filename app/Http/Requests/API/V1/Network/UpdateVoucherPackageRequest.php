<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        if (is_string($this->package_color)) {
            $this->merge(['package_color' => trim($this->package_color)]);
        }
    }

    public function rules(): array
    {
        $packageId = $this->route('id') ?? ($this->route('voucher_package')?->package_id ?? $this->route('voucher_package'));

        // Scope name uniqueness to the package's own network (consistent with Store rule).
        $networkId = null;
        if ($packageId) {
            $existingPackage = \App\Models\VoucherPackage::find($packageId);
            $networkId = $existingPackage?->network_id;
        }

        $uniqueNameRule = Rule::unique('voucher_packages', 'package_name')
            ->ignore($packageId, 'package_id')
            ->whereNull('deleted_at');
        if ($networkId !== null) {
            $uniqueNameRule = $uniqueNameRule->where('network_id', $networkId);
        }

        return [
            'package_name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                $uniqueNameRule,
            ],
            'package_color' => [
                'nullable',
                'string',
                'max:20',
            ],
            'package_balance' => [
                'nullable',
                'string',
                'max:20',
            ],
            'package_uptime' => [
                'nullable',
                'string',
                'max:20',
            ],
            'package_validity' => [
                'nullable',
                'string',
                'max:20',
            ],
            'package_voucher_count' => [
                'nullable',
                'integer',
                'min:1',
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
