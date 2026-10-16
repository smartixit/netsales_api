<?php

namespace App\Http\Requests\API\V1\Request;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestItemRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_count' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}
