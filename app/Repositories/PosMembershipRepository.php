<?php

namespace App\Repositories;

use App\Models\PosUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Data access for scoped membership records.
 */
class PosMembershipRepository extends BaseRepository
{
    public function __construct(PosUser $model)
    {
        parent::__construct($model);
    }

    public function getMembersForPos(int $posId, array $filters = []): CursorPaginator
    {
        $result = $this->getFiltered(
            array_merge($filters, ['pos_id' => $posId]),
            ['user', 'role']
        );

        if (! $result instanceof CursorPaginator) {
            throw new LogicException('POS membership lists must use cursor pagination.');
        }

        return $result;
    }

    /**
     * @return array{total:int,active:int,suspended:int,invited:int}
     */
    public function getSummaryForPos(int $posId): array
    {
        $summary = $this->model->newQuery()
            ->where('pos_id', $posId)
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as suspended, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as invited',
                ['ACTIVE', 'SUSPENDED', 'INVITED']
            )
            ->first();

        return [
            'total' => (int) ($summary?->getAttribute('total') ?? 0),
            'active' => (int) ($summary?->getAttribute('active') ?? 0),
            'suspended' => (int) ($summary?->getAttribute('suspended') ?? 0),
            'invited' => (int) ($summary?->getAttribute('invited') ?? 0),
        ];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (! empty($filters['pos_id'])) {
            $query->where('pos_id', (int) $filters['pos_id']);
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
