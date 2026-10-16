<?php

namespace App\Http\Requests\API\V1\Pos;

use App\Enums\PosStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pos_name' => is_string($this->pos_name) ? trim($this->pos_name) : $this->pos_name,
            'pos_email' => is_string($this->pos_email) ? trim(strtolower($this->pos_email)) : $this->pos_email,
        ]);
    }

    public function rules(): array
    {
        return [
            'pos_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('pos', 'pos_name')->whereNull('deleted_at'),
            ],
            'pos_email' => [
                'nullable',
                'string',
                'email',
                'max:100',
                Rule::unique('pos', 'pos_email')->whereNull('deleted_at'),
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
                'nullable',
                Rule::enum(PosStatus::class),
            ],
        ];
    }
}
