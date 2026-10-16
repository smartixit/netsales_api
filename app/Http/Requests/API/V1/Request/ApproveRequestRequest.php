<?php

namespace App\Http\Requests\API\V1\Request;

use App\Enums\SaleType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveRequestRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_note' => [
                'nullable',
                'string',
                'max:150',
            ],
            'sale_type' => [
                'nullable',
                Rule::enum(SaleType::class),
            ],
            'items' => [
                'nullable',
                'array',
                'min:1',
            ],
            'items.*.package_id' => [
                'required_with:items',
                'integer',
                Rule::exists('voucher_packages', 'package_id')->whereNull('deleted_at'),
            ],
            'items.*.voucher_count' => [
                'required_with:items',
                'integer',
                'min:1',
            ],
            'items.*.voucher_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'items.*.item_discount' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],
        ];
    }

    public function getRequestNote(): ?string
    {
        return $this->input('request_note');
    }

    public function getSaleType(): ?SaleType
    {
        $saleType = $this->input('sale_type');

        return $saleType ? SaleType::tryFrom((string) $saleType) : null;
    }

    public function hasItems(): bool
    {
        return $this->has('items') && is_array($this->input('items')) && ! empty($this->input('items'));
    }

    /**
     * @return array<int, array{package_id: int, voucher_count: int, voucher_price?: float, item_discount?: float}>
     */
    public function getItems(): array
    {
        return (array) $this->input('items', []);
    }
}
