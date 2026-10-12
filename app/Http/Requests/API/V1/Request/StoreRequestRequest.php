<?php

namespace App\Http\Requests\API\V1\Request;

use App\Enums\RequestStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network_id' => [
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'pos_id' => [
                'required',
                'integer',
                Rule::exists('pos', 'pos_id')->whereNull('deleted_at'),
            ],
            'request_note' => [
                'nullable',
                'string',
                'max:150',
            ],
            'status' => [
                'nullable',
                Rule::enum(RequestStatus::class),
            ],
            'items' => [
                'nullable',
                'array',
            ],
            'items.*.package_id' => [
                'required_with:items',
                'integer',
                Rule::exists('voucher_packages', 'package_id')->whereNull('deleted_at'),
            ],
            'items.*.item_voucher_count' => [
                'required_with:items',
                'integer',
                'min:1',
            ],
        ];
    }
}
