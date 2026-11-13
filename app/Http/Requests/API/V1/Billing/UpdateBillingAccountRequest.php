<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingAccountRequest extends FormRequest
{
    use LoadsCustomValidationMessages;
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes','nullable','string','max:160'],
            'billing_email' => ['sometimes','nullable','email','max:160'],
            'tax_number' => ['sometimes','nullable','string','max:80'],
            'billing_address' => ['sometimes','nullable','string','max:5000'],
            'payment_terms_days' => ['sometimes','integer','min:0','max:365'],
        ];
    }
}
