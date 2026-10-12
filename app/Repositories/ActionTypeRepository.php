<?php

namespace App\Repositories;

use App\Interfaces\ActionTypeRepositoryInterface;
use App\Models\ActionType;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

class ActionTypeRepository extends BaseRepository implements ActionTypeRepositoryInterface
{
    public function __construct(ActionType $model)
    {
        parent::__construct($model);
    }

    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $query = $this->model->newQuery();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['status'])) {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('action_type_name')->get();
        }

        return $query->orderBy('action_type_id', 'desc')->cursorPaginate($perPage);
    }

    public function findByKey(string $key): ?ActionType
    {
        return $this->model->newQuery()->forKey($key)->first();
    }

    public function hasPermissions(int $id): bool
    {
        $actionType = $this->model->find($id);

        return $actionType ? $actionType->permissions->exists() : false;
    }
}
