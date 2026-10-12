<?php

namespace App\Http\Requests\API\V1\Request;

use App\Enums\RequestStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'required',
                Rule::enum(RequestStatus::class),
            ],
            'request_note' => [
                'nullable',
                'string',
                'max:150',
            ],
        ];
    }
}
