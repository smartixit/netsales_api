<?php

namespace App\Http\Requests\API\V1\ActionType;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActionTypeRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action_type_key' => is_string($this->action_type_key) ? trim(strtolower($this->action_type_key)) : $this->action_type_key,
            'action_type_name' => is_string($this->action_type_name) ? trim($this->action_type_name) : $this->action_type_name,
            'action_type_desc' => is_string($this->action_type_desc) ? trim($this->action_type_desc) : $this->action_type_desc,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $actionTypeId = $this->route('action_type')?->action_type_id ?? $this->route('action_type') ?? $this->route('id');

        return [
            'action_type_key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('action_types', 'action_type_key')
                    ->ignore($actionTypeId, 'action_type_id')
                    ->whereNull('deleted_at'),
            ],
            'action_type_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'action_type_desc' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }
}
