<?php

namespace App\Http\Requests\API\V1\Request;

use App\Enums\RequestStatus;
use App\Enums\SaleType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestRequest extends FormRequest
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
                'nullable',
                Rule::enum(SaleType::class),
            ],
            'request_note' => [
                'nullable',
                'string',
                'max:150',
            ],
            'status' => [
                'nullable',
                Rule::enum(RequestStatus::class),
            ],
            'items' => [
                'sometimes',
                'required',
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
            // NOTE: voucher_price & item_discount NO LONGER accepted from client (backend uses catalog/effective).
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

    public function getStatus(): ?RequestStatus
    {
        $status = $this->input('status');

        return $status ? RequestStatus::tryFrom((string) $status) : null;
    }

    public function hasItems(): bool
    {
        return $this->has('items') && is_array($this->input('items'));
    }

    /**
     * @return array<int, array{package_id: int, voucher_count: int, voucher_price?: float, item_discount?: float}>|null
     */
    public function getItems(): ?array
    {
        return $this->hasItems() ? (array) $this->input('items') : null;
    }
}
