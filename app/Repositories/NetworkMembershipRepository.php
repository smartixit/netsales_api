<?php

namespace App\Repositories;

use App\Models\NetworkUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Data access for scoped Network memberships.
 *
 * @extends BaseRepository<NetworkUser>
 */
class NetworkMembershipRepository extends BaseRepository
{
    public function __construct(NetworkUser $model)
    {
        parent::__construct($model);
    }

    public function getMembersForNetwork(int $networkId, array $filters = []): CursorPaginator
    {
        $result = $this->getFiltered(
            array_merge($filters, ['network_id' => $networkId]),
            ['user', 'role']
        );

        if (! $result instanceof CursorPaginator) {
            throw new LogicException('Network membership lists must use cursor pagination.');
        }

        return $result;
    }

    /**
     * @return array{total:int,active:int,suspended:int,invited:int}
     */
    public function getSummaryForNetwork(int $networkId): array
    {
        $summary = $this->model->newQuery()
            ->where('network_id', $networkId)
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as suspended, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as invited',
                ['ACTIVE', 'SUSPENDED', 'INVITED']
            )
            ->first();

        return [
            'total' => (int) ($summary?->total ?? 0),
            'active' => (int) ($summary?->active ?? 0),
            'suspended' => (int) ($summary?->suspended ?? 0),
            'invited' => (int) ($summary?->invited ?? 0),
        ];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $membershipQuery) use ($search): void {
            $membershipQuery
                ->whereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery
                        ->where('user_full_name', 'like', "%{$search}%")
                        ->orWhere('user_phone_number', 'like', "%{$search}%");
                })
                ->orWhereHas('role', function (Builder $roleQuery) use ($search): void {
                    $roleQuery
                        ->where('role_name', 'like', "%{$search}%")
                        ->orWhere('role_key', 'like', "%{$search}%");
                });

            if (ctype_digit($search)) {
                $membershipQuery
                    ->orWhere('id', (int) $search)
                    ->orWhere('user_id', (int) $search);
            }
        });
    }
}
