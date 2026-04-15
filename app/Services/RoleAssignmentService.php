<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAssignmentService
{
    /**
     * @return Collection<int, Role>
     */
    public function allowedRolesForEntity(Entity $entity): Collection
    {
        $entity->loadMissing('group.roles');

        return $entity->group->roles->sortBy('name')->values();
    }

    public function assignToEntity(User $user, Entity $entity, string $roleName): void
    {
        $allowedRole = $this->allowedRolesForEntity($entity)->firstWhere('name', $roleName);

        if (! $allowedRole) {
            throw new RuntimeException("Role [{$roleName}] is not allowed for group [{$entity->group->code}].");
        }

        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->whereNull('entity_id')
            ->firstOrFail();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            $user->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    public function removeFromEntity(User $user, Entity $entity, string $roleName): void
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->whereNull('entity_id')
            ->first();

        if (! $role) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            $user->removeRole($role);
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }
}
