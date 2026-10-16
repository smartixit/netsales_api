<?php

namespace App\Http\Requests\API\V1\SaleInvoice;

use App\Enums\SaleType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'sale_type' => [
                'required',
                Rule::enum(SaleType::class),
            ],
        ];
    }

    public function getSaleType(): SaleType
    {
        return SaleType::from((string) $this->input('sale_type'));
    }
}
