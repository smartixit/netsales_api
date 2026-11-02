<?php

namespace App\Http\Requests\API\V1\Request;

use App\Enums\SaleType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewRequestModificationRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_type' => [
                'prohibited',
            ],
            'request_note' => [
                'nullable',
                'string',
                'max:250',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.package_id' => [
                'required',
                'integer',
                Rule::exists('voucher_packages', 'package_id')->whereNull('deleted_at'),
            ],
            'items.*.voucher_count' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.pricing_token' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function getSaleType(): ?SaleType
    {
        $saleType = $this->input('sale_type');

        return $saleType ? SaleType::tryFrom((string) $saleType) : null;
    }

    public function getRequestNote(): ?string
    {
        return $this->input('request_note');
    }

    /**
     * @return array<int, array{package_id: int, voucher_count: int, pricing_token?: string|null}>
     */
    public function getItems(): array
    {
        return (array) $this->input('items', []);
    }
}
