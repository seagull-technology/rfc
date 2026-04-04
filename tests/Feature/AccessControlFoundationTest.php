<?php

namespace Tests\Feature;

use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_control_seed_creates_core_groups_entities_and_roles(): void
    {
        $this->seed(AccessControlSeeder::class);

        $this->assertDatabaseHas('groups', [
            'code' => 'authorities',
        ]);

        $this->assertDatabaseHas('groups', [
            'code' => 'rfc',
        ]);

        $this->assertDatabaseHas('entities', [
            'code' => 'rfc-jordan',
        ]);

        $this->assertDatabaseHas('entities', [
            'code' => 'ministry-of-interior',
        ]);

        $role = Role::query()->where('name', 'authority_approver')->firstOrFail();

        $this->assertDatabaseHas('group_role', [
            'role_id' => $role->getKey(),
        ]);
    }

    public function test_rfc_approver_role_receives_expected_permissions(): void
    {
        $this->seed(AccessControlSeeder::class);

        $role = Role::query()->where('name', 'rfc_approver')->firstOrFail();

        $this->assertTrue($role->hasPermissionTo('applications.approve'));
        $this->assertTrue($role->hasPermissionTo('applications.reject'));
        $this->assertTrue($role->hasPermissionTo('permits.issue'));
        $this->assertFalse($role->hasPermissionTo('users.manage'));
    }
}
