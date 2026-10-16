<?php

namespace App\Http\Requests\API\V1\Network;

use App\Enums\NetworkStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNetworkRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'network_name' => is_string($this->network_name) ? trim($this->network_name) : $this->network_name,
        ]);
    }

    public function rules(): array
    {
        return [
            'network_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('networks', 'network_name')->whereNull('deleted_at'),
            ],
            'governorate_id' => [
                'required',
                'integer',
                Rule::exists('governorates', 'governorate_id')->whereNull('deleted_at'),
            ],
            'directorate_id' => [
                'required',
                'integer',
                Rule::exists('directorates', 'directorate_id')
                    ->where('governorate_id', $this->governorate_id)
                    ->whereNull('deleted_at'),
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
                'nullable',
                Rule::enum(NetworkStatus::class),
            ],
        ];
    }
}
