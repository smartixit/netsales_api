<?php

namespace App\Services;

use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class PlatformRoleReferenceService
{
    /**
     * @return Collection<int, Role>
     */
    public function getActivePlatformRoles(): Collection
    {
        return Role::query()
            ->where('scope', RoleScope::PLATFORM->value)
            ->active()
            ->orderBy('role_name')
            ->get();
    }
}
