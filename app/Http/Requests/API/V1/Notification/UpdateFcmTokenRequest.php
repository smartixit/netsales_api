<?php

namespace App\Http\Requests\API\V1\Notification;

use App\Enums\DeviceType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب تسجيل وتحديث رمز الإشعارات الفورية للجهاز (FCM Push Token).
 *
 * يلتزم بمعايير الكود النظيف وخلوّه التام من دوال $request->validated().
 */
class UpdateFcmTokenRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'string', 'max:255'],
            'fcm_token' => ['required', 'string', 'min:10', 'max:1000'],
            'device_name' => ['nullable', 'string', 'max:50'],
            'device_type' => ['nullable', 'string', Rule::in(array_map(fn (DeviceType $t) => $t->value, DeviceType::cases()))],
        ];
    }

    public function getDeviceUuid(): string
    {
        return (string) $this->input('device_uuid');
    }

    public function getFcmToken(): string
    {
        return (string) $this->input('fcm_token');
    }

    public function getDeviceName(): ?string
    {
        return $this->input('device_name');
    }

    public function getDeviceType(): ?string
    {
        return $this->input('device_type');
    }
}
