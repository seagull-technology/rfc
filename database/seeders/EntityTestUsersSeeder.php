<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EntityTestUsersSeeder extends Seeder
{
    private const PASSWORD = 'Test@12345';

    /**
     * @var array<string, array<int, string>>
     */
    private const GROUP_ROLES = [
        'admins' => ['platform_admin'],
        'rfc' => ['rfc_admin', 'rfc_intake_officer', 'rfc_reviewer', 'rfc_approver'],
        'authorities' => ['authority_reviewer', 'authority_approver'],
        'organizations' => ['applicant_owner'],
        'individuals' => ['applicant_owner'],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereNull('entity_id')
            ->get()
            ->keyBy('name');

        $entities = Entity::query()
            ->with('group')
            ->where('status', 'active')
            ->whereNotNull('code')
            ->orderBy('id')
            ->get();

        foreach ($entities as $entity) {
            $roleNames = self::GROUP_ROLES[$entity->group?->code] ?? [];

            if ($roleNames === []) {
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['email' => $this->emailFor($entity)],
                [
                    'name' => 'Test '.$entity->displayName('en'),
                    'username' => $this->usernameFor($entity),
                    'national_id' => $this->nationalIdFor($entity),
                    'phone' => $this->phoneFor($entity),
                    'status' => 'active',
                    'registration_type' => $entity->group?->code === 'authorities' || $entity->group?->code === 'rfc' || $entity->group?->code === 'admins'
                        ? 'staff'
                        : ($entity->registration_type ?: 'company'),
                    'password' => Hash::make(self::PASSWORD),
                ],
            );

            $entity->users()->syncWithoutDetaching([
                $user->getKey() => [
                    'job_title' => 'Testing Account',
                    'is_primary' => false,
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            ]);

            $registrar->setPermissionsTeamId($entity->getKey());

            try {
                foreach ($roleNames as $roleName) {
                    $role = $roles->get($roleName);

                    if ($role) {
                        $user->assignRole($role);
                    }
                }
            } finally {
                $registrar->setPermissionsTeamId(null);
            }
        }

        $registrar->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'Created or updated %d entity test user(s). Shared password: %s',
            $entities->count(),
            self::PASSWORD,
        ));
    }

    private function emailFor(Entity $entity): string
    {
        return 'entity.'.$this->slugFor($entity).'@rfc.test';
    }

    private function usernameFor(Entity $entity): string
    {
        return Str::limit('test_'.$this->slugFor($entity), 42, '').'_'.$entity->getKey();
    }

    private function nationalIdFor(Entity $entity): string
    {
        return sprintf('88%08d', $entity->getKey());
    }

    private function phoneFor(Entity $entity): string
    {
        return sprintf('078%07d', $entity->getKey());
    }

    private function slugFor(Entity $entity): string
    {
        return Str::of($entity->code ?: 'entity-'.$entity->getKey())
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();
    }
}
