<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingAccountContactRequest extends FormRequest
{
    use LoadsCustomValidationMessages;
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => ['sometimes','required','string','max:120'],
            'email' => ['sometimes','nullable','email','max:160'],
            'phone' => ['sometimes','nullable','string','max:40'],
            'is_primary' => ['sometimes','boolean'],
            'receives_invoices' => ['sometimes','boolean'],
        ];
    }
}
