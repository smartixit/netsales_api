<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNetworkPosRequest extends FormRequest
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
            'pos_uuid' => [
                'required',
                'string',
                Rule::exists('pos', 'pos_uuid')->whereNull('deleted_at'),
                Rule::unique('network_pos', 'pos_id')
                    ->where('pos_id', function ($query) {
                        $query->select('pos_id')
                            ->from('pos')
                            ->where('pos_uuid', $this->pos_uuid);
                    })
                    ->where('network_id', $this->network_id),
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function getNetworkId(): int
    {
        return (int) $this->network_id;
    }

    public function getPosUuid(): string
    {
        return (int) $this->pos_uuid;
    }

    public function getStatus(): bool
    {
        return $this->has('status') ? (bool) $this->status : true;
    }
}
