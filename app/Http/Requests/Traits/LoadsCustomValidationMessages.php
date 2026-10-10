<?php

namespace App\Http\Requests\Traits;

use Illuminate\Support\Arr;

trait LoadsCustomValidationMessages
{
    /**
     * Automatically load custom validation messages defined in
     * lang/{locale}/validation.php under 'custom.{RequestClassName}'.
     */
    public function messages(): array
    {
        $custom = __('validation.custom.' . class_basename($this));

        return is_array($custom) ? Arr::dot($custom) : [];
    }
}
