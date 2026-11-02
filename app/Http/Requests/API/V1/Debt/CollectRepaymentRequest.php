<?php

namespace App\Http\Requests\API\V1\Debt;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class CollectRepaymentRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network_id' => ['required', 'integer', 'exists:networks,network_id'],
            'pos_id' => ['required', 'integer', 'exists:pos,pos_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function getNetworkId(): int
    {
        return (int) $this->input('network_id');
    }

    public function getPosId(): int
    {
        return (int) $this->input('pos_id');
    }

    public function getAmount(): float
    {
        return round((float) $this->input('amount'), 2);
    }

    public function getPaymentMethod(): string
    {
        return (string) ($this->input('payment_method') ?: 'CASH');
    }

    public function getReferenceNumber(): ?string
    {
        return $this->input('reference_number');
    }

    public function getNotes(): ?string
    {
        return $this->input('notes');
    }
}

