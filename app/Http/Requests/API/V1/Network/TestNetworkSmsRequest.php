<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class TestNetworkSmsRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number' => is_string($this->phone_number) ? trim($this->phone_number) : $this->phone_number,
            'message' => is_string($this->message) ? trim($this->message) : $this->message,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:20'],
            'message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function getPhoneNumber(): string
    {
        return (string) $this->phone_number;
    }

    public function getCustomMessage(): ?string
    {
        return $this->message ? (string) $this->message : null;
    }
}
