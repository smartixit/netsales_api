<?php

namespace App\Http\Requests\API\V1\Request;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestItemRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => [
                'required',
                'integer',
                Rule::exists('requests', 'request_id')->whereNull('deleted_at'),
            ],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('voucher_packages', 'package_id')->whereNull('deleted_at'),
            ],
            'voucher_count' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}
