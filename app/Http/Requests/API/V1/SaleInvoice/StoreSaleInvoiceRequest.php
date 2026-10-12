<?php

namespace App\Http\Requests\API\V1\SaleInvoice;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleInvoiceRequest extends FormRequest
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
            'pos_id' => [
                'required',
                'integer',
                Rule::exists('pos', 'pos_id')->whereNull('deleted_at'),
            ],
            'invoice_sale_type' => [
                'nullable',
                'string',
                'max:50',
            ],
            'items' => [
                'nullable',
                'array',
            ],
            'items.*.package_id' => [
                'required_with:items',
                'integer',
                Rule::exists('voucher_packages', 'package_id')->whereNull('deleted_at'),
            ],
            'items.*.voucher_price' => [
                'required_with:items',
                'numeric',
                'min:0',
            ],
            'items.*.voucher_count' => [
                'required_with:items',
                'integer',
                'min:1',
            ],
            'items.*.item_discount' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],
        ];
    }
}
