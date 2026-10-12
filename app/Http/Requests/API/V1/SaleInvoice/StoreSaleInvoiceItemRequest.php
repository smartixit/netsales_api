<?php

namespace App\Http\Requests\API\V1\SaleInvoice;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleInvoiceItemRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => [
                'required',
                'integer',
                Rule::exists('sale_invoices', 'invoice_id')->whereNull('deleted_at'),
            ],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('voucher_packages', 'package_id')->whereNull('deleted_at'),
            ],
            'voucher_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'voucher_count' => [
                'required',
                'integer',
                'min:1',
            ],
            'item_discount' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],
        ];
    }
}
