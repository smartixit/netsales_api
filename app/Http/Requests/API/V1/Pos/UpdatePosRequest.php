<?php

namespace App\Http\Requests\API\V1\Pos;

use App\Enums\PosStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePosRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if (is_string($this->pos_name)) {
            $data['pos_name'] = trim($this->pos_name);
        }
        if (is_string($this->pos_holder_name)) {
            $data['pos_holder_name'] = trim($this->pos_holder_name);
        }
        if (is_string($this->pos_phone_number)) {
            $data['pos_phone_number'] = trim($this->pos_phone_number);
        }
        if (is_string($this->pos_email)) {
            $data['pos_email'] = trim(strtolower($this->pos_email));
        }
        if (! empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $posId = $this->route('id') ?? ($this->route('pos')?->pos_id ?? $this->route('pos'));

        return [
            'pos_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('pos', 'pos_name')
                    ->ignore($posId, 'pos_id')
                    ->whereNull('deleted_at'),
            ],
            'pos_holder_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'pos_phone_number' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
                // Rule::unique('pos', 'pos_phone_number')
                //     ->ignore($posId, 'pos_id')
                //     ->whereNull('deleted_at'),
            ],
            'pos_email' => [
                'nullable',
                'string',
                'email',
                'max:100',
                Rule::unique('pos', 'pos_email')
                    ->ignore($posId, 'pos_id')
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
            'pos_address' => [
                'nullable',
                'string',
                'max:150',
            ],
            'pos_profile_logo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
            'status' => [
                'sometimes',
                Rule::enum(PosStatus::class),
            ],
        ];
    }
}
