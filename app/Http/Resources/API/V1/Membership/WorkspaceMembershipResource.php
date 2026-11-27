<?php

namespace App\Http\Resources\API\V1\Membership;

use App\Enums\MembershipStatus;
use App\Http\Resources\API\V1\RBAC\RoleResource;
use App\Http\Resources\API\V1\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable API representation shared by Network and POS membership records.
 *
 * @property-read mixed $resource
 */
class WorkspaceMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $this->resource;
        $rawStatus = $membership->getAttribute('status');
        $status = $rawStatus instanceof MembershipStatus
            ? $rawStatus->value
            : (string) $rawStatus;

        return [
            'id' => (int) $membership->getKey(),
            'network_id' => $membership->getAttribute('network_id'),
            'pos_id' => $membership->getAttribute('pos_id'),
            'user_id' => (int) $membership->getAttribute('user_id'),
            'role_id' => (int) $membership->getAttribute('role_id'),
            'status' => $status,
            'status_label' => MembershipStatus::tryFrom($status)?->label(),
            'last_event_seq' => (int) ($membership->getAttribute('last_event_seq') ?? 1),
            'is_owner' => (bool) ($membership->getAttribute('is_owner') ?? false),
            'created_at' => $membership->getAttribute('created_at')?->toISOString(),
            'updated_at' => $membership->getAttribute('updated_at')?->toISOString(),
            'user' => $this->whenLoaded(
                'user',
                fn () => new UserResource($membership->getRelation('user'))
            ),
            'role' => $this->whenLoaded(
                'role',
                fn () => new RoleResource($membership->getRelation('role'))
            ),
        ];
    }
}
