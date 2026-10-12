<?php

namespace App\Http\Requests\API\V1\SaleInvoice;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleInvoiceItemRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'voucher_count' => [
                'sometimes',
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
