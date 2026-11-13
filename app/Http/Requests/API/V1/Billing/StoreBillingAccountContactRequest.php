<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreBillingAccountContactRequest extends FormRequest
{
    use LoadsCustomValidationMessages;
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => ['required','string','max:120'],
            'email' => ['nullable','email','max:160'],
            'phone' => ['nullable','string','max:40'],
            'is_primary' => ['sometimes','boolean'],
            'receives_invoices' => ['sometimes','boolean'],
        ];
    }
}
