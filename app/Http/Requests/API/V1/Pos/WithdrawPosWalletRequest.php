<?php

namespace App\Http\Requests\API\V1\Pos;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawPosWalletRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'statement' => [
                'required',
                'string',
                'max:255',
            ],
            'log_date' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function getAmount(): float
    {
        return (float) $this->input('amount');
    }

    public function getStatement(): string
    {
        return (string) $this->input('statement');
    }

    public function getLogDate(): ?string
    {
        return $this->input('log_date');
    }
}
