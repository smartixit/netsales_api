<?php

namespace App\Http\Requests\API\V1\Pos;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferNetworkToPosWalletRequest extends FormRequest
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
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'pos_id' => [
                'required',
                'integer',
                Rule::exists('pos', 'pos_id')->whereNull('deleted_at'),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'statement' => [
                'nullable',
                'string',
                'max:255',
            ],
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
        return (float) $this->input('amount');
    }

    public function getStatement(): string
    {
        return (string) $this->input('statement', '');
    }
}
