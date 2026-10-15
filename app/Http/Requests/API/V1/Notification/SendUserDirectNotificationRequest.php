<?php

namespace App\Http\Requests\API\V1\Notification;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب إرسال إشعار موجه لمستخدم محدد من الإدارة عبر قنوات مختارة.
 */
class SendUserDirectNotificationRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:1000'],
            'channels' => ['nullable', 'array'],
            'channels.*' => Rule::in(['in_app', 'fcm', 'push', 'sms']),
        ];
    }

    public function getUserId(): int
    {
        return (int) $this->input('user_id');
    }

    public function getTitle(): string
    {
        return (string) $this->input('title');
    }

    public function getBody(): string
    {
        return (string) $this->input('body');
    }

    public function getChannels(): array
    {
        $channels = $this->input('channels');
        if (empty($channels) || ! is_array($channels)) {
            return ['in_app', 'fcm'];
        }

        return $channels;
    }
}
