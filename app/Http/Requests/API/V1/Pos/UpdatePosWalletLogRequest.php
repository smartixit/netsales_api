<?php

namespace App\Http\Requests\API\V1\Pos;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePosWalletLogRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'log_statement' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
