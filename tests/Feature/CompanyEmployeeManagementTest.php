<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyEmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_owner_can_create_employee_without_delete_access(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$owner, $entity] = $this->createCompanyContext();

        $response = $this->actingAs($owner)->post(route('company.employees.store'), [
            'name' => 'Company Creator',
            'email' => 'creator@company.test',
            'phone' => '0795555001',
            'national_id' => '1234567890',
            'job_title' => 'Producer',
            'role' => 'company_creator',
            'password' => 'Creator@12345',
            'password_confirmation' => 'Creator@12345',
        ]);

        $employee = User::query()->where('email', 'creator@company.test')->firstOrFail();

        $response->assertRedirect(route('company.employees.index'));
        $this->assertDatabaseHas('entity_user', [
            'entity_id' => $entity->getKey(),
            'user_id' => $employee->getKey(),
            'job_title' => 'Producer',
            'is_primary' => false,
            'status' => 'active',
        ]);
        $this->assertTrue($employee->roleNamesForEntity($entity)->contains('company_creator'));
        $this->assertDatabaseHas('user_role_assignment_audits', [
            'user_id' => $employee->getKey(),
            'entity_id' => $entity->getKey(),
            'changed_by_user_id' => $owner->getKey(),
            'role_name' => 'company_creator',
            'action' => 'added',
        ]);

        $this->actingAs($owner)
            ->get(route('company.employees.index'))
            ->assertOk()
            ->assertSeeText('Company Creator')
            ->assertSeeText(__('app.company.employees.no_delete_hint'))
            ->assertDontSeeText('Delete');
    }

    public function test_legacy_primary_company_owner_without_role_can_access_company_users(): void
    {
        $this->refreshApplicationWithLocale('ar');
        $this->seed(AccessControlSeeder::class);

        [$owner] = $this->createCompanyContext(assignOwnerRole: false);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText(__('app.portal.profile_links.company_employees'));

        $this->actingAs($owner)
            ->get(route('company.employees.index'))
            ->assertOk()
            ->assertSeeText(__('app.company.employees.title'));
    }

    public function test_non_primary_company_employee_without_role_cannot_access_company_users(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [, $entity] = $this->createCompanyContext();
        $employee = User::query()->create([
            'name' => 'Unprivileged Employee',
            'username' => 'unprivileged-employee',
            'email' => 'unprivileged.employee@example.com',
            'status' => 'active',
            'registration_type' => 'company',
            'password' => Hash::make('Employee@12345'),
        ]);
        $employee->entities()->attach($entity->getKey(), [
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($employee)
            ->get(route('company.employees.index'))
            ->assertForbidden();
    }

    public function test_company_employee_visibility_is_limited_to_own_requests_unless_role_allows_company_wide_access(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$owner, $entity] = $this->createCompanyContext();
        $creator = $this->createCompanyEmployee($entity, 'company_creator', [
            'name' => 'Creator Employee',
            'username' => 'creator-employee',
            'email' => 'creator.employee@example.com',
        ]);
        $manager = $this->createCompanyEmployee($entity, 'company_manager', [
            'name' => 'Manager Employee',
            'username' => 'manager-employee',
            'email' => 'manager.employee@example.com',
        ]);

        $ownerApplication = $this->createCompanyApplication($entity, $owner, [
            'code' => 'REQ-OWNER',
            'project_name' => 'Owner Project',
        ]);
        $creatorApplication = $this->createCompanyApplication($entity, $creator, [
            'code' => 'REQ-CREATOR',
            'project_name' => 'Creator Project',
        ]);

        $this->actingAs($creator)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSeeText('Creator Project')
            ->assertDontSeeText('Owner Project');

        $this->actingAs($creator)
            ->get(route('applications.show', $ownerApplication))
            ->assertForbidden();

        $this->actingAs($creator)
            ->get(route('applications.show', $creatorApplication))
            ->assertOk()
            ->assertSeeText('Creator Project');

        $this->actingAs($manager)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSeeText('Creator Project')
            ->assertSeeText('Owner Project');
    }

    /**
     * @return array{0: User, 1: Entity}
     */
    private function createCompanyContext(bool $assignOwnerRole = true): array
    {
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $owner = User::query()->create([
            'name' => 'Company Owner',
            'username' => 'company-owner',
            'email' => 'company.owner@example.com',
            'phone' => '0795555000',
            'status' => 'active',
            'registration_type' => 'company',
            'password' => Hash::make('Owner@12345'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Company Studio',
            'name_ar' => 'Company Studio',
            'registration_no' => 'COMP-100',
            'email' => 'studio@company.test',
            'phone' => '0795555111',
            'status' => 'active',
            'registration_type' => 'company',
            'metadata' => [
                'address' => 'Amman, Jordan',
            ],
        ]);

        $owner->entities()->attach($entity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        if ($assignOwnerRole) {
            $this->assignRoleForEntity($owner, $entity, 'applicant_owner');
        }

        return [$owner, $entity];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCompanyEmployee(Entity $entity, string $roleName, array $overrides = []): User
    {
        $employee = User::query()->create(array_merge([
            'name' => 'Company Employee',
            'username' => 'company-employee',
            'email' => 'company.employee@example.com',
            'phone' => null,
            'status' => 'active',
            'registration_type' => 'company',
            'password' => Hash::make('Employee@12345'),
        ], $overrides));

        $employee->entities()->attach($entity->getKey(), [
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assignRoleForEntity($employee, $entity, $roleName);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCompanyApplication(Entity $entity, User $submitter, array $overrides = []): Application
    {
        return Application::query()->create(array_merge([
            'code' => 'REQ-TEST',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $submitter->getKey(),
            'project_name' => 'Test Project',
            'project_nationality' => 'jordanian',
            'project_nationalities' => ['jordanian'],
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => now()->addMonth()->toDateString(),
            'planned_end_date' => now()->addMonth()->addDays(5)->toDateString(),
            'estimated_crew_count' => 10,
            'estimated_budget' => 25000,
            'project_summary' => 'A company request.',
            'status' => 'draft',
            'current_stage' => 'draft',
        ], $overrides));
    }

    private function assignRoleForEntity(User $user, Entity $entity, string $roleName): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            $user->assignRole($roleName);
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }
}
