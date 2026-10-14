<?php

namespace App\Http\Requests\API\V1\Backup;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class DriveTokenRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', 'string'],
            'code' => ['required', 'string'],
        ];
    }

    public function getState(): string
    {
        return (string) $this->state;
    }

    public function getCode(): string
    {
        return (string) $this->code;
    }
}
