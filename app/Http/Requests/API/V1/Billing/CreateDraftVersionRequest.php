<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class CreateDraftVersionRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trial_policy_version_id' => ['nullable', 'integer', 'exists:billing_trial_policy_versions,trial_policy_version_id'],
            'dunning_policy_version_id' => ['nullable', 'integer', 'exists:billing_dunning_policy_versions,dunning_policy_version_id'],
        ];
    }
}
