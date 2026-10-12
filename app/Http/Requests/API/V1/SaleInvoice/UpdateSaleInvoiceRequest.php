<?php

namespace App\Http\Requests\API\V1\SaleInvoice;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleInvoiceRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_sale_type' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }
}
