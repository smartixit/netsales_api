<?php

namespace App\Http\Requests\API\V1\Notification;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * طلب إرسال إشعار إداري لجمهور محدد عبر قنوات متعددة.
 */
class SendAdminNotificationRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audience' => ['required', 'array'],
            'audience.type' => ['required', Rule::in(['all', 'role', 'network', 'users'])],
            'audience.role_id' => ['required_if:audience.type,role', 'nullable', 'integer', 'exists:roles,role_id'],
            'audience.network_id' => ['required_if:audience.type,network', 'nullable', 'integer', 'exists:networks,network_id'],
            'audience.user_ids' => ['required_if:audience.type,users', 'nullable', 'array', 'min:1'],
            'audience.user_ids.*' => ['integer', 'exists:users,user_id'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => Rule::in(['in_app', 'sms', 'email', 'fcm', 'push']),
            'category' => ['nullable', 'string', Rule::in(['SYSTEM', 'INFO', 'WARNING', 'SECURITY'])],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function getAudience(): array
    {
        return (array) $this->input('audience', []);
    }

    public function getChannels(): array
    {
        return (array) $this->input('channels', ['in_app']);
    }

    public function getTitle(): string
    {
        return (string) $this->input('title');
    }

    public function getBody(): string
    {
        return (string) $this->input('body');
    }

    public function getCategory(): ?string
    {
        return $this->input('category');
    }

    public function getImage(): ?UploadedFile
    {
        return $this->file('image');
    }
}
