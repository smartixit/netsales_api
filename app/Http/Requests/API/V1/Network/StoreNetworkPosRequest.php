<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNetworkPosRequest extends FormRequest
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
                Rule::unique('network_pos', 'pos_id')
                    ->where('network_id', $this->network_id)
                    ->whereNull('deleted_at'),
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
