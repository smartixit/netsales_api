<?php

namespace App\Http\Requests\API\V1\Geography;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDirectorateRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->directorate_name)) {
            $this->merge(['directorate_name' => trim($this->directorate_name)]);
        }
    }

    public function rules(): array
    {
        $directorateId = $this->route('directorate')?->directorate_id ?? $this->route('directorate');

        return [
            'governorate_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('governorates', 'governorate_id')->whereNull('deleted_at'),
            ],
            'directorate_name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('directorates', 'directorate_name')
                    ->ignore($directorateId, 'directorate_id')
                    ->where(function ($query) {
                        $govId = $this->governorate_id ?? $this->route('directorate')?->governorate_id;
                        if ($govId) {
                            $query->where('governorate_id', $govId);
                        }
                    })
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
