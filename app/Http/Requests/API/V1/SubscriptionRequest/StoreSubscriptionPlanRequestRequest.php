<?php

namespace App\Http\Requests\API\V1\SubscriptionRequest;

use App\Enums\PaymentType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequestRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network_id' => [
                'required',
                'integer',
                'exists:networks,network_id',
            ],
            'plan_id' => [
                'required',
                'integer',
                'exists:subscription_plans,plan_id',
            ],
            'request_payment_type' => [
                'required',
                Rule::enum(PaymentType::class),
            ],
            'account_id' => [
                'nullable',
                'required_if:request_payment_type,BANK',
                'integer',
                'exists:bank_accounts,account_id',
            ],
            'transaction_reference' => [
                'nullable',
                'string',
                'max:50',
            ],
            'transaction_receipt' => [
                'nullable',
                'file',
                'mimes:jpeg,png,jpg,webp,pdf',
                'max:5120',
            ],
            'request_note' => [
                'nullable',
                'string',
                'max:150',
            ],
        ];
    }

    public function getNetworkId(): int
    {
        return (int) $this->input('network_id');
    }

    public function getPlanId(): int
    {
        return (int) $this->input('plan_id');
    }

    public function getRequestPaymentType(): string
    {
        return (string) $this->input('request_payment_type', PaymentType::CASH->value);
    }

    public function getAccountId(): ?int
    {
        return $this->filled('account_id') ? (int) $this->input('account_id') : null;
    }

    public function getTransactionReference(): ?string
    {
        return $this->filled('transaction_reference') ? (string) $this->input('transaction_reference') : null;
    }

    public function getRequestNote(): ?string
    {
        return $this->filled('request_note') ? (string) $this->input('request_note') : null;
    }
}
