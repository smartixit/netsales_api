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
            'package_color' => is_string($this->package_color) ? trim($this->package_color) : $this->package_color,
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
                'max:50',
                Rule::unique('voucher_packages', 'package_name')
                    ->where('network_id', $this->network_id)
                    ->whereNull('deleted_at'),
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

    public function getNetworkId(): int
    {
        return (int) $this->network_id;
    }

    public function getPackageName(): string
    {
        return (string) $this->package_name;
    }

    public function getPackageColor(): string
    {
        return (string) ($this->package_color ?? '0xFF0D7A5F');
    }

    public function getPackageBalance(): ?string
    {
        return $this->package_balance ? (string) $this->package_balance : null;
    }

    public function getPackageUptime(): ?string
    {
        return $this->package_uptime ? (string) $this->package_uptime : null;
    }

    public function getPackageValidity(): ?string
    {
        return $this->package_validity ? (string) $this->package_validity : null;
    }

    public function getPackageVoucherCount(): int
    {
        return $this->has('package_voucher_count') ? (int) $this->package_voucher_count : 51;
    }

    public function getPackageVoucherPrice(): float
    {
        return (float) $this->package_voucher_price;
    }

    public function getPackageDefaultCashDiscount(): float
    {
        return (float) $this->package_default_cash_discount;
    }

    public function getPackageDefaultCreditDiscount(): float
    {
        return (float) $this->package_default_credit_discount;
    }

    public function getStatus(): bool
    {
        return $this->has('status') ? (bool) $this->status : true;
    }
}
