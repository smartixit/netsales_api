<?php

namespace App\Http\Requests\API\V1\Notification;

use App\Enums\NotificationType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationRequest extends FormRequest
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
                'sometimes',
                'required',
                'integer',
                Rule::exists('users', 'user_id')->whereNull('deleted_at'),
            ],
            'notification_type' => [
                'sometimes',
                'required',
                Rule::enum(NotificationType::class),
            ],
            'notification_title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'notification_message' => [
                'sometimes',
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
                'sometimes',
                'required',
                'boolean',
            ],
            'read_at' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function getUserId(): ?int
    {
        return $this->has('user_id') ? (int) $this->user_id : null;
    }

    public function getNotificationType(): ?NotificationType
    {
        if (! $this->has('notification_type')) {
            return null;
        }

        return $this->notification_type instanceof NotificationType
            ? $this->notification_type
            : NotificationType::from((string) $this->notification_type);
    }

    public function getNotificationTitle(): ?string
    {
        return $this->has('notification_title') ? (string) $this->notification_title : null;
    }

    public function getNotificationMessage(): ?string
    {
        return $this->has('notification_message') ? (string) $this->notification_message : null;
    }

    public function getNotificationImage(): ?string
    {
        return $this->has('notification_image') ? ($this->notification_image ? (string) $this->notification_image : null) : null;
    }

    public function getIsRead(): ?bool
    {
        return $this->has('is_read') ? (bool) $this->is_read : null;
    }

    public function getReadAt(): ?string
    {
        return $this->has('read_at') ? ($this->read_at ? (string) $this->read_at : null) : null;
    }
}
