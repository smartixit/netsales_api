<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
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
        ];
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
}
