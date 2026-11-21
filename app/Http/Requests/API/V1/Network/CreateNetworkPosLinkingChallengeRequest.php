<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\PosStatus;
use Illuminate\Validation\Rule;

class CreateNetworkPosLinkingChallengeRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('pos_id') && $this->filled('pos_uuid')) {
                $message = 'يجب تحديد نقطة البيع بمعرف واحد فقط.';
                $validator->errors()->add('pos_id', $message);
                $validator->errors()->add('pos_uuid', $message);
            }
        });
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
                'required_without:pos_uuid',
                'integer',
                'min:1',
                Rule::exists('pos', 'pos_id')
                    ->whereNull('deleted_at')
                    ->where('status', PosStatus::ACTIVE->value),
            ],
            'pos_uuid' => [
                'required_without:pos_id',
                'string',
                'max:64',
                'regex:/^POS-\d{4}-\d{5}-[A-F0-9]{4}$/',
                Rule::exists('pos', 'pos_uuid')
                    ->whereNull('deleted_at')
                    ->where('status', PosStatus::ACTIVE->value),
            ],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:5',
                'max:60',
            ],
        ];
    }

    public function getNetworkId(): int
    {
        return (int) $this->network_id;
    }

    public function getPosId(): ?int
    {
        return $this->filled('pos_id') ? (int) $this->pos_id : null;
    }

    public function getPosUuid(): ?string
    {
        return $this->filled('pos_uuid') ? (string) $this->pos_uuid : null;
    }

    public function getDurationMinutes(): int
    {
        return $this->filled('duration_minutes') ? (int) $this->duration_minutes : 15;
    }
}
