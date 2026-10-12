<?php

namespace App\Interfaces;

use App\Models\ActionType;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

interface ActionTypeRepositoryInterface extends BaseRepositoryInterface
{
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    public function findByKey(string $key): ?ActionType;

    public function hasPermissions(int $id): bool;
}
