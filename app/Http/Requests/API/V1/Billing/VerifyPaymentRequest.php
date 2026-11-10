<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'treasury_account_id' => ['required', 'integer', 'exists:treasury_accounts,treasury_account_id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
