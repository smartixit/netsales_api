<?php

namespace App\Http\Requests\API\V1\Network;

use App\Models\Network;
use App\Models\NetworkUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NetworkWorkspaceOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $networkId = (int) $this->route('networkId');

        if (! $user || $networkId <= 0) {
            return false;
        }

        $network = Network::query()->find($networkId);

        if (! $network || ! Gate::forUser($user)->allows('view', $network)) {
            return false;
        }

        $isOwner = (int) $network->owner_id === (int) $user->user_id;
        $isActiveMember = NetworkUser::query()
            ->where('network_id', $networkId)
            ->where('user_id', $user->user_id)
            ->where('status', 'ACTIVE')
            ->exists();

        if (! $isOwner && ! $isActiveMember) {
            return false;
        }

        return $user->hasPermission('networks.view', $networkId);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['7d', '30d', '90d', '12m'])],
        ];
    }

    public function period(): string
    {
        return (string) $this->validated('period', '30d');
    }
}
