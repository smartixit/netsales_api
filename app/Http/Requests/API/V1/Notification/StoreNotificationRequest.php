<?php

namespace App\Http\Requests\API\V1\Notification;

use App\Enums\NotificationType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notification_title' => is_string($this->notification_title) ? trim($this->notification_title) : $this->notification_title,
            'notification_message' => is_string($this->notification_message) ? trim($this->notification_message) : $this->notification_message,
            'notification_image' => is_string($this->notification_image) ? trim($this->notification_image) : $this->notification_image,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'user_id')->whereNull('deleted_at'),
            ],
            'notification_type' => [
                'required',
                Rule::enum(NotificationType::class),
            ],
            'notification_title' => [
                'required',
                'string',
                'max:255',
            ],
            'notification_message' => [
                'required',
                'string',
                'max:5000',
            ],
            'notification_image' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_read' => [
                'nullable',
                'boolean',
            ],
            'read_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
