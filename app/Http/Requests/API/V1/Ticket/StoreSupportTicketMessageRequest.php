<?php

namespace App\Http\Requests\API\V1\Ticket;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketMessageRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'message_body' => is_string($this->message_body) ? trim($this->message_body) : $this->message_body,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ticket_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('support_tickets', 'ticket_id')->whereNull('deleted_at'),
            ],
            'message_body' => [
                'required',
                'string',
                'max:5000',
            ],
            'sent_at' => [
                'nullable',
                'date',
            ],
            'is_read' => [
                'nullable',
                'boolean',
            ],
            'read_at' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function getTicketId(): ?int
    {
        return $this->filled('ticket_id') ? (int) $this->input('ticket_id') : null;
    }

    public function getMessageBody(): string
    {
        return (string) $this->input('message_body');
    }
}
