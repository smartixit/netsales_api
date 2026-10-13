<?php

namespace App\Http\Requests\API\V1\User;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * طلب التحقق لرفع وتحديث الصورة الشخصية للمستخدم (Update Profile Picture Request).
 */
class UpdateProfilePictureRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profile_picture' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
        ];
    }

    public function getProfilePictureFile(): UploadedFile
    {
        return $this->file('profile_picture');
    }
}
