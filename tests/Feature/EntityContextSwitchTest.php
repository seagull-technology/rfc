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

class EntityContextSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_current_entity_and_dashboard_scope_follows_it(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $group = Group::query()->where('code', 'organizations')->firstOrFail();
        $user = User::query()->create([
            'name' => 'Multi Entity User',
            'username' => 'multi-entity-user',
            'email' => 'multi-entity-user@example.com',
            'national_id' => '8888777766',
            'phone' => '0795666777',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $primaryEntity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Alpha Studio',
            'name_ar' => 'Alpha Studio',
            'registration_no' => 'ORG-ALPHA',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'alpha@example.com',
            'phone' => '0795000001',
        ]);

        $secondaryEntity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Beta Studio',
            'name_ar' => 'Beta Studio',
            'registration_no' => 'ORG-BETA',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'beta@example.com',
            'phone' => '0795000002',
        ]);

        $primaryEntity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $secondaryEntity->users()->attach($user->getKey(), [
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($primaryEntity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId($secondaryEntity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        Application::query()->create([
            'code' => 'REQ-ALPHA',
            'entity_id' => $primaryEntity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Alpha Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-06-01',
            'planned_end_date' => '2026-06-05',
            'project_summary' => 'Primary entity request.',
            'status' => 'submitted',
            'submitted_at' => now()->subDay(),
        ]);

        Application::query()->create([
            'code' => 'REQ-BETA',
            'entity_id' => $secondaryEntity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'project_name' => 'Beta Project',
            'project_nationality' => 'jordanian',
            'work_category' => 'documentary',
            'release_method' => 'festival',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-04',
            'project_summary' => 'Secondary entity request.',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $switchResponse = $this->actingAs($user)->post(route('context.entity.update'), [
            'entity_id' => $secondaryEntity->getKey(),
        ]);

        $switchResponse
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('current_entity_id', $secondaryEntity->getKey());

        $dashboardResponse = $this->withSession([
            'current_entity_id' => $secondaryEntity->getKey(),
        ])->actingAs($user)->get(route('dashboard'));

        $dashboardResponse
            ->assertOk()
            ->assertSeeText('Beta Studio')
            ->assertSeeText('REQ-BETA')
            ->assertDontSeeText('REQ-ALPHA');

        $applicationsResponse = $this->withSession([
            'current_entity_id' => $secondaryEntity->getKey(),
        ])->actingAs($user)->get(route('applications.index'));

        $applicationsResponse
            ->assertOk()
            ->assertSeeText('REQ-BETA')
            ->assertDontSeeText('REQ-ALPHA');
    }

    public function test_login_by_registration_number_prefers_the_matched_entity_context(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $group = Group::query()->where('code', 'organizations')->firstOrFail();
        $user = User::query()->create([
            'name' => 'Registration Login User',
            'username' => 'registration-login-user',
            'email' => 'registration-login-user@example.com',
            'national_id' => '7777666655',
            'phone' => '0795777888',
            'registration_type' => 'company',
            'status' => 'active',
            'password' => Hash::make('Password@123'),
        ]);

        $primaryEntity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Gamma Studio',
            'name_ar' => 'Gamma Studio',
            'registration_no' => 'ORG-GAMMA',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'gamma@example.com',
            'phone' => '0795111111',
        ]);

        $matchedEntity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Delta Studio',
            'name_ar' => 'Delta Studio',
            'registration_no' => 'ORG-DELTA',
            'registration_type' => 'company',
            'status' => 'active',
            'email' => 'delta@example.com',
            'phone' => '0795222222',
        ]);

        $primaryEntity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $matchedEntity->users()->attach($user->getKey(), [
            'is_primary' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($primaryEntity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId($matchedEntity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->post(route('login.store'), [
            'identifier' => 'ORG-DELTA',
            'password' => 'Password@123',
        ]);

        $response
            ->assertRedirect(route('otp.create'))
            ->assertSessionHas('pending_auth_entity_id', $matchedEntity->getKey());
    }
}
