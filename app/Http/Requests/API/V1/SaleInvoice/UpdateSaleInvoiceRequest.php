<?php

namespace App\Http\Requests\API\V1\SaleInvoice;

use App\Enums\PaymentType;
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
            'invoice_sale_type' => [
                'required',
                Rule::enum(PaymentType::class),
            ],
        ];
    }

    public function getSaleType(): PaymentType
    {
        return PaymentType::from((string) $this->input('invoice_sale_type'));
    }
}
