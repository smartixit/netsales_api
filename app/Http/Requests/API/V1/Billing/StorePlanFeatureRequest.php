<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Enums\BillingFeatureType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanFeatureRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feature_key' => ['required', 'string', 'alpha_dash', 'max:100'],
            'name' => ['required', 'string', 'max:120'],
            'feature_type' => ['required', Rule::enum(BillingFeatureType::class)],
            'value' => ['required'],
            'unit' => ['nullable', 'string', 'max:40'],
            'aggregation' => ['nullable', 'in:SUM,COUNT,LAST,MAX,MIN'],
            'reset_period' => ['nullable', 'in:DAY,WEEK,MONTH,YEAR,NEVER'],
            'overage_behavior' => ['nullable', 'in:BLOCK,TRACK,INVOICE'],
            'configuration' => ['nullable', 'array'],
            'configuration.overage_unit_size_micros' => ['required_if:overage_behavior,INVOICE', 'integer', 'min:1'],
            'configuration.overage_unit_amount_minor' => ['required_if:overage_behavior,INVOICE', 'integer', 'min:0'],
            'configuration.overage_currency_id' => ['required_if:overage_behavior,INVOICE', 'integer', 'exists:currencies,currency_id'],
        ];
    }
}
