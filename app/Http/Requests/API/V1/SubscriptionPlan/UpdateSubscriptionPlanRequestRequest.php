<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Enums\PaymentType;
use App\Enums\RequestStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequestRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'transaction_reference' => is_string($this->transaction_reference) ? trim($this->transaction_reference) : $this->transaction_reference,
            'transaction_receipt' => is_string($this->transaction_receipt) ? trim($this->transaction_receipt) : $this->transaction_receipt,
            'rejection_reason' => is_string($this->rejection_reason) ? trim($this->rejection_reason) : $this->rejection_reason,
            'request_note' => is_string($this->request_note) ? trim($this->request_note) : $this->request_note,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'network_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'plan_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('subscription_plans', 'plan_id')->whereNull('deleted_at'),
            ],
            'request_total_amount' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'request_payment_type' => [
                'sometimes',
                'required',
                Rule::enum(PaymentType::class),
            ],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('bank_accounts', 'account_id')->whereNull('deleted_at'),
            ],
            'transaction_reference' => [
                'nullable',
                'string',
                'max:100',
            ],
            'transaction_receipt' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::enum(RequestStatus::class),
            ],
            'rejection_reason' => [
                'nullable',
                'string',
                'max:500',
            ],
            'review_date' => [
                'nullable',
                'date',
            ],
            'request_note' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
