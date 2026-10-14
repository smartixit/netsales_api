<?php

namespace App\Http\Requests\API\V1\Ticket;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ticket_subject' => is_string($this->ticket_subject) ? trim($this->ticket_subject) : $this->ticket_subject,
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
            'pos_id' => [
                'nullable',
                'integer',
                Rule::exists('pos', 'pos_id')->whereNull('deleted_at'),
            ],
            'ticket_subject' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function getNetworkId(): ?int
    {
        return $this->filled('network_id') ? (int) $this->input('network_id') : null;
    }

    public function getPosId(): ?int
    {
        return $this->filled('pos_id') ? (int) $this->input('pos_id') : null;
    }

    public function getTicketSubject(): ?string
    {
        return $this->filled('ticket_subject') ? (string) $this->input('ticket_subject') : null;
    }
}
