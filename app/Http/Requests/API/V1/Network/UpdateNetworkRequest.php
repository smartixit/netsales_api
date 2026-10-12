<?php

namespace App\Http\Requests\API\V1\Network;

use App\Enums\NetworkStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNetworkRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if (is_string($this->network_name)) {
            $data['network_name'] = trim($this->network_name);
        }
        if (is_string($this->network_phone)) {
            $data['network_phone'] = trim($this->network_phone);
        }
        if (! empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $networkId = $this->route('network')?->network_id ?? $this->route('network');

        return [
            'network_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('networks', 'network_name')
                    ->ignore($networkId, 'network_id')
                    ->whereNull('deleted_at'),
            ],
            'governorate_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('governorates', 'governorate_id')->whereNull('deleted_at'),
            ],
            'directorate_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('directorates', 'directorate_id')->whereNull('deleted_at'),
            ],
            'network_phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
                // Rule::unique('networks', 'network_phone')
                //     ->ignore($networkId, 'network_id')
                //     ->whereNull('deleted_at'),
            ],
            'network_address' => [
                'nullable',
                'string',
                'max:150',
            ],
            'network_logo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
            'status' => [
                'sometimes',
                Rule::enum(NetworkStatus::class),
            ],
        ];
    }
}
