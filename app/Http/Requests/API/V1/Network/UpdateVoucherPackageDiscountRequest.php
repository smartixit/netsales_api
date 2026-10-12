<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherPackageDiscountRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_cash' => [
                'sometimes',
                'required',
                'numeric',
                'between:0,100',
            ],
            'discount_credit' => [
                'sometimes',
                'required',
                'numeric',
                'between:0,100',
            ],
            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
