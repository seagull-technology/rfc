<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApprovalRoutingRule;
use App\Models\ApprovalRoutingRuleAudit;
use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApprovalRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_approval_routing_rule_from_admin_panel(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.approval-routing.store'), [
            'name' => 'Public security to GAM',
            'request_type' => 'application',
            'approval_code' => 'public_security',
            'target_entity_id' => $targetEntity->getKey(),
            'priority' => 20,
            'is_active' => '1',
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['feature_film'],
                'release_methods' => ['cinema'],
            ],
        ]);

        $response->assertRedirect(route('admin.approval-routing.index'));

        $this->assertDatabaseHas('approval_routing_rules', [
            'name' => 'Public security to GAM',
            'request_type' => 'application',
            'approval_code' => 'public_security',
            'target_entity_id' => $targetEntity->getKey(),
            'priority' => 20,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('approval_routing_rule_audits', [
            'rule_name' => 'Public security to GAM',
            'action' => 'created',
            'changed_by_user_id' => $admin->getKey(),
        ]);
    }

    public function test_application_submission_uses_dynamic_target_entity_for_authority_approval(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$applicant] = $this->createApplicantContext();
        [$targetAuthorityUser, $targetEntity] = $this->createAuthorityContextForEntity('greater-amman-municipality', [
            'email' => 'gam-reviewer@example.com',
            'username' => 'gam-reviewer',
        ]);
        [$defaultAuthorityUser] = $this->createAuthorityContextForEntity('public-security-directorate', [
            'email' => 'psd-reviewer@example.com',
            'username' => 'psd-reviewer',
        ]);

        ApprovalRoutingRule::query()
            ->where('request_type', 'application')
            ->where('approval_code', 'public_security')
            ->update(['is_active' => false]);

        ApprovalRoutingRule::query()->create([
            'name' => 'Route public security to GAM',
            'request_type' => 'application',
            'approval_code' => 'public_security',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['feature_film'],
                'release_methods' => ['cinema'],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();

        $response = $this->actingAs($applicant)->post(route('applications.submit', $application));

        $response->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseHas('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'public_security',
            'entity_id' => $targetEntity->getKey(),
            'status' => 'pending',
        ]);

        $this->assertDatabaseMissing('application_authority_approvals', [
            'application_id' => $application->getKey(),
            'authority_code' => 'public_security',
            'entity_id' => Entity::query()->where('code', 'public-security-directorate')->value('id'),
        ]);

        $this->assertTrue($targetAuthorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_requested'
        ));
        $this->assertFalse($defaultAuthorityUser->fresh()->unreadNotifications->contains(
            fn ($notification) => data_get($notification->data, 'type_key') === 'authority_approval_requested'
        ));
    }

    public function test_targeted_authority_can_open_inbox_for_non_default_routing_entity(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        [$applicant] = $this->createApplicantContext();
        [$targetAuthorityUser, $targetEntity] = $this->createAuthorityContextForEntity('greater-amman-municipality', [
            'email' => 'gam2-reviewer@example.com',
            'username' => 'gam2-reviewer',
        ]);

        ApprovalRoutingRule::query()
            ->where('request_type', 'application')
            ->where('approval_code', 'public_security')
            ->update(['is_active' => false]);

        ApprovalRoutingRule::query()->create([
            'name' => 'Public security to municipality for cinema work',
            'request_type' => 'application',
            'approval_code' => 'public_security',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'release_methods' => ['cinema'],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($applicant)->post(route('applications.store'), $this->applicationPayload([
            'project_name' => 'Dynamic City Shoot',
            'required_approvals' => ['public_security'],
        ]));

        $application = Application::query()->firstOrFail();

        $this->actingAs($applicant)->post(route('applications.submit', $application));

        $indexResponse = $this->actingAs($targetAuthorityUser)->get(route('authority.applications.index'));

        $indexResponse
            ->assertOk()
            ->assertSeeText('Dynamic City Shoot');

        $showResponse = $this->actingAs($targetAuthorityUser)->get(route('authority.applications.show', $application));

        $showResponse
            ->assertOk()
            ->assertSeeText('Dynamic City Shoot')
            ->assertSeeText($targetEntity->displayName());
    }

    public function test_approval_routing_show_page_displays_audit_history_for_rule_changes(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $firstTarget = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();
        $secondTarget = Entity::query()->where('code', 'ministry-of-interior')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.approval-routing.store'), [
            'name' => 'Drones to municipality',
            'request_type' => 'application',
            'approval_code' => 'drones',
            'target_entity_id' => $firstTarget->getKey(),
            'priority' => 40,
            'is_active' => '1',
            'conditions' => [
                'project_nationalities' => ['international'],
                'work_categories' => ['documentary'],
                'release_methods' => ['festival'],
            ],
        ]);

        $rule = ApprovalRoutingRule::query()->where('name', 'Drones to municipality')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.approval-routing.update', $rule), [
            'name' => 'Drones to interior',
            'request_type' => 'application',
            'approval_code' => 'drones',
            'target_entity_id' => $secondTarget->getKey(),
            'priority' => 15,
            'is_active' => '1',
            'conditions' => [
                'project_nationalities' => ['international'],
                'work_categories' => ['feature_film'],
                'release_methods' => ['cinema'],
            ],
        ]);

        $rule->refresh();

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.show', $rule));

        $response
            ->assertOk()
            ->assertSeeText('Drones to interior')
            ->assertSeeText(__('app.admin.approval_routing.audit_actions.created'))
            ->assertSeeText(__('app.admin.approval_routing.audit_actions.updated'))
            ->assertSeeText($admin->displayName());

        $this->assertSame(2, ApprovalRoutingRuleAudit::query()->where('approval_routing_rule_id', $rule->getKey())->count());
    }

    public function test_super_admin_can_toggle_approval_routing_rule_status_and_audit_it(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $rule = ApprovalRoutingRule::query()->create([
            'name' => 'Toggle municipalities route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [],
            'priority' => 90,
            'is_active' => true,
        ]);

        $deactivateResponse = $this->actingAs($admin)->post(route('admin.approval-routing.status', $rule), [
            'is_active' => 0,
        ]);

        $deactivateResponse->assertRedirect();
        $this->assertDatabaseHas('approval_routing_rules', [
            'id' => $rule->getKey(),
            'is_active' => 0,
        ]);

        $activateResponse = $this->actingAs($admin)->post(route('admin.approval-routing.status', $rule), [
            'is_active' => 1,
        ]);

        $activateResponse->assertRedirect();
        $this->assertDatabaseHas('approval_routing_rules', [
            'id' => $rule->getKey(),
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('approval_routing_rule_audits', [
            'approval_routing_rule_id' => $rule->getKey(),
            'action' => 'deactivated',
            'changed_by_user_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('approval_routing_rule_audits', [
            'approval_routing_rule_id' => $rule->getKey(),
            'action' => 'activated',
            'changed_by_user_id' => $admin->getKey(),
        ]);
    }

    public function test_super_admin_cannot_create_duplicate_active_rule_with_same_signature(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        ApprovalRoutingRule::query()->create([
            'name' => 'Existing municipalities rule',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['feature_film'],
                'release_methods' => ['cinema'],
            ],
            'priority' => 25,
            'is_active' => true,
        ]);
        $countBefore = ApprovalRoutingRule::query()->where('approval_code', 'municipalities')->count();

        $response = $this->from(route('admin.approval-routing.create'))
            ->actingAs($admin)
            ->post(route('admin.approval-routing.store'), [
                'name' => 'Duplicate municipalities rule',
                'request_type' => 'application',
                'approval_code' => 'municipalities',
                'target_entity_id' => $targetEntity->getKey(),
                'priority' => 30,
                'is_active' => '1',
                'conditions' => [
                    'project_nationalities' => ['jordanian'],
                    'work_categories' => ['feature_film'],
                    'release_methods' => ['cinema'],
                ],
            ]);

        $response
            ->assertRedirect(route('admin.approval-routing.create'))
            ->assertSessionHasErrors('approval_code');

        $this->assertSame($countBefore, ApprovalRoutingRule::query()->where('approval_code', 'municipalities')->count());
        $this->assertDatabaseMissing('approval_routing_rules', [
            'name' => 'Duplicate municipalities rule',
        ]);
    }

    public function test_index_conflict_report_flags_shadowed_narrow_rule(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        ApprovalRoutingRule::query()->create([
            'name' => 'Broad municipalities route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film', 'documentary'],
                'release_methods' => [],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        ApprovalRoutingRule::query()->create([
            'name' => 'Feature film municipalities route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film'],
                'release_methods' => [],
            ],
            'priority' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.index'));

        $response
            ->assertOk()
            ->assertSeeText('Priority Conflict Report')
            ->assertSeeText('Shadowed rule')
            ->assertSeeText('Broad municipalities route')
            ->assertSeeText('Feature film municipalities route')
            ->assertSeeText('Tighten the broader rule or raise the narrower rule priority if it is meant to change the routing outcome.');
    }

    public function test_index_conflict_report_flags_same_priority_overlap(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        ApprovalRoutingRule::query()->create([
            'name' => 'Cinema municipalities route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['feature_film', 'documentary'],
                'release_methods' => ['cinema'],
            ],
            'priority' => 30,
            'is_active' => true,
        ]);

        ApprovalRoutingRule::query()->create([
            'name' => 'Documentary cinema municipalities route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['documentary'],
                'release_methods' => ['cinema', 'festival'],
            ],
            'priority' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.index'));

        $response
            ->assertOk()
            ->assertSeeText('Priority Conflict Report')
            ->assertSeeText('Same-priority overlap')
            ->assertSeeText('Cinema municipalities route')
            ->assertSeeText('Documentary cinema municipalities route')
            ->assertSeeText('Give one rule a clearer priority or separate their conditions so the same application never depends on tie ordering.');
    }

    public function test_index_can_filter_rule_directory_to_only_shadowed_risky_rules(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        ApprovalRoutingRule::query()->create([
            'name' => 'Broad heritage route',
            'request_type' => 'application',
            'approval_code' => 'heritage',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film', 'documentary'],
                'release_methods' => [],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        ApprovalRoutingRule::query()->create([
            'name' => 'Feature heritage route',
            'request_type' => 'application',
            'approval_code' => 'heritage',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film'],
                'release_methods' => [],
            ],
            'priority' => 40,
            'is_active' => true,
        ]);

        ApprovalRoutingRule::query()->create([
            'name' => 'Safe environment route',
            'request_type' => 'application',
            'approval_code' => 'environment',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['international'],
                'work_categories' => ['series'],
                'release_methods' => ['streaming'],
            ],
            'priority' => 70,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.index', [
            'risk' => 'shadowed_rule',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Shadowed rule')
            ->assertSeeText('Broad heritage route')
            ->assertSeeText('Feature heritage route');
    }

    public function test_super_admin_can_bulk_deactivate_risky_rules_from_conflict_report(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $broadRule = ApprovalRoutingRule::query()->create([
            'name' => 'Broad municipalities bulk route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film', 'documentary'],
                'release_methods' => [],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $narrowRule = ApprovalRoutingRule::query()->create([
            'name' => 'Narrow municipalities bulk route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film'],
                'release_methods' => [],
            ],
            'priority' => 30,
            'is_active' => true,
        ]);

        $safeRule = ApprovalRoutingRule::query()->create([
            'name' => 'Safe heritage route',
            'request_type' => 'application',
            'approval_code' => 'heritage',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['international'],
                'work_categories' => ['series'],
                'release_methods' => ['streaming'],
            ],
            'priority' => 70,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.approval-routing.bulk-status'), [
            'rule_ids' => [$broadRule->getKey(), $narrowRule->getKey()],
            'is_active' => 0,
            'redirect_risk' => 'shadowed_rule',
        ]);

        $response->assertRedirect(route('admin.approval-routing.index', [
            'risk' => 'shadowed_rule',
        ]));

        $this->assertDatabaseHas('approval_routing_rules', [
            'id' => $broadRule->getKey(),
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('approval_routing_rules', [
            'id' => $narrowRule->getKey(),
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('approval_routing_rules', [
            'id' => $safeRule->getKey(),
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('approval_routing_rule_audits', [
            'approval_routing_rule_id' => $broadRule->getKey(),
            'action' => 'deactivated',
            'changed_by_user_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('approval_routing_rule_audits', [
            'approval_routing_rule_id' => $narrowRule->getKey(),
            'action' => 'deactivated',
            'changed_by_user_id' => $admin->getKey(),
        ]);
    }

    public function test_create_page_can_start_from_duplicated_rule_as_inactive_draft(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $rule = ApprovalRoutingRule::query()->create([
            'name' => 'Original municipalities route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['feature_film'],
                'release_methods' => ['cinema'],
            ],
            'priority' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.create', [
            'duplicate_rule_id' => $rule->getKey(),
        ]));

        $response
            ->assertOk()
            ->assertSeeText('You are starting from a copy of Original municipalities route.')
            ->assertSee('value="Original municipalities route Draft"', false)
            ->assertSee('value="20"', false)
            ->assertDontSee('name="is_active" type="checkbox" class="form-check-input" value="1" checked', false);
    }

    public function test_edit_page_explains_rule_terminology_and_shows_related_risk_details(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        ApprovalRoutingRule::query()->create([
            'name' => 'Broad heritage edit route',
            'request_type' => 'application',
            'approval_code' => 'heritage',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film', 'documentary'],
                'release_methods' => [],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $rule = ApprovalRoutingRule::query()->create([
            'name' => 'Narrow heritage edit route',
            'request_type' => 'application',
            'approval_code' => 'heritage',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film'],
                'release_methods' => [],
            ],
            'priority' => 40,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.edit', $rule));

        $response
            ->assertOk()
            ->assertSeeText('How Routing Rules Work')
            ->assertSeeText('Why we call it a rule')
            ->assertSeeText('Why This Rule Is Risky')
            ->assertSeeText('Shadowed rule')
            ->assertSeeText('Broad heritage edit route')
            ->assertSeeText('This rule is the one being affected.');
    }

    public function test_index_and_show_pages_display_rule_usage_analytics(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant, $entity] = $this->createApplicantContext();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $usedRule = ApprovalRoutingRule::query()->create([
            'name' => 'Used municipalities analytics route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [],
            'priority' => 10,
            'is_active' => true,
        ]);

        $unusedRule = ApprovalRoutingRule::query()->create([
            'name' => 'Unused municipalities analytics route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'work_categories' => ['documentary'],
            ],
            'priority' => 20,
            'is_active' => true,
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-AN-1001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Analytics Match',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-01',
            'planned_end_date' => '2026-07-10',
            'project_summary' => 'For rule analytics.',
            'status' => 'submitted',
            'current_stage' => 'authority_review',
            'metadata' => [
                'requirements' => [
                    'required_approvals' => ['municipalities'],
                ],
            ],
        ]);

        $secondApplication = Application::query()->create([
            'code' => 'REQ-AN-1002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Analytics Match Two',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-07-11',
            'planned_end_date' => '2026-07-20',
            'project_summary' => 'For rule analytics two.',
            'status' => 'submitted',
            'current_stage' => 'authority_review',
            'metadata' => [
                'requirements' => [
                    'required_approvals' => ['municipalities'],
                ],
            ],
        ]);

        \App\Models\ApplicationAuthorityApproval::query()->create([
            'application_id' => $application->getKey(),
            'authority_code' => 'municipalities',
            'entity_id' => $targetEntity->getKey(),
            'approval_routing_rule_id' => $usedRule->getKey(),
            'status' => 'approved',
        ]);

        \App\Models\ApplicationAuthorityApproval::query()->create([
            'application_id' => $secondApplication->getKey(),
            'authority_code' => 'municipalities',
            'entity_id' => $targetEntity->getKey(),
            'approval_routing_rule_id' => $usedRule->getKey(),
            'status' => 'pending',
        ]);

        $indexResponse = $this->actingAs($admin)->get(route('admin.approval-routing.index'));

        $indexResponse
            ->assertOk()
            ->assertSeeText('Routing Usage Snapshot')
            ->assertSeeText('Used municipalities analytics route')
            ->assertSeeText('2')
            ->assertSeeText('1');

        $showResponse = $this->actingAs($admin)->get(route('admin.approval-routing.show', $usedRule));

        $showResponse
            ->assertOk()
            ->assertSeeText('Usage count')
            ->assertSeeText('Usage by status')
            ->assertSeeText('Pending: 1')
            ->assertSeeText('Approved: 1');

        $unusedShowResponse = $this->actingAs($admin)->get(route('admin.approval-routing.show', $unusedRule));

        $unusedShowResponse
            ->assertOk()
            ->assertSeeText('Usage count')
            ->assertSeeText('0');
    }

    public function test_index_displays_cleanup_review_for_active_rule_maintenance(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant, $entity] = $this->createApplicantContext();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $unusedRule = ApprovalRoutingRule::query()->create([
            'name' => 'Cleanup unused rule',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [],
            'priority' => 10,
            'is_active' => true,
        ]);

        $staleRuleId = \Illuminate\Support\Facades\DB::table('approval_routing_rules')->insertGetId([
            'name' => 'Cleanup stale rule',
            'request_type' => 'application',
            'approval_code' => 'heritage',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => json_encode([]),
            'priority' => 20,
            'is_active' => true,
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);
        $staleRule = ApprovalRoutingRule::query()->findOrFail($staleRuleId);

        $recentRule = ApprovalRoutingRule::query()->create([
            'name' => 'Cleanup recent rule',
            'request_type' => 'application',
            'approval_code' => 'environment',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [],
            'priority' => 30,
            'is_active' => true,
        ]);

        $applicationOne = Application::query()->create([
            'code' => 'REQ-CL-1001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Cleanup One',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-10',
            'project_summary' => 'Cleanup analytics one.',
            'status' => 'submitted',
            'current_stage' => 'authority_review',
            'metadata' => [
                'requirements' => [
                    'required_approvals' => ['heritage'],
                ],
            ],
        ]);

        $applicationTwo = Application::query()->create([
            'code' => 'REQ-CL-1002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Cleanup Two',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-08-11',
            'planned_end_date' => '2026-08-20',
            'project_summary' => 'Cleanup analytics two.',
            'status' => 'submitted',
            'current_stage' => 'authority_review',
            'metadata' => [
                'requirements' => [
                    'required_approvals' => ['environment'],
                ],
            ],
        ]);

        \Illuminate\Support\Facades\DB::table('application_authority_approvals')->insert([
            'application_id' => $applicationOne->getKey(),
            'authority_code' => 'heritage',
            'entity_id' => $targetEntity->getKey(),
            'approval_routing_rule_id' => $staleRule->getKey(),
            'status' => 'approved',
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);

        \App\Models\ApplicationAuthorityApproval::query()->create([
            'application_id' => $applicationTwo->getKey(),
            'authority_code' => 'environment',
            'entity_id' => $targetEntity->getKey(),
            'approval_routing_rule_id' => $recentRule->getKey(),
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('application_authority_approvals', [
            'authority_code' => 'heritage',
            'approval_routing_rule_id' => $staleRule->getKey(),
        ]);
        $this->assertDatabaseHas('application_authority_approvals', [
            'authority_code' => 'environment',
            'approval_routing_rule_id' => $recentRule->getKey(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.index', [
            'cleanup' => 'all',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Cleanup Review')
            ->assertSeeText('Cleanup unused rule')
            ->assertSeeText('Cleanup stale rule')
            ->assertSeeText('Unused active rule')
            ->assertSeeText('Stale active rule');
    }

    public function test_preview_endpoint_returns_matching_applications_for_current_rule_inputs(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [, $entity] = $this->createApplicantContext();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        Application::query()->create([
            'code' => 'REQ-10001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $admin->getKey(),
            'project_name' => 'Preview Match',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-05-01',
            'planned_end_date' => '2026-05-10',
            'project_summary' => 'Matched by preview',
            'status' => 'submitted',
            'current_stage' => 'authority_review',
            'metadata' => [
                'requirements' => [
                    'required_approvals' => ['public_security'],
                ],
            ],
        ]);

        Application::query()->create([
            'code' => 'REQ-10002',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $admin->getKey(),
            'project_name' => 'Preview Mismatch',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'festival',
            'planned_start_date' => '2026-05-01',
            'planned_end_date' => '2026-05-10',
            'project_summary' => 'Should not match preview',
            'status' => 'submitted',
            'current_stage' => 'authority_review',
            'metadata' => [
                'requirements' => [
                    'required_approvals' => ['public_security'],
                ],
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.approval-routing.preview'), [
            'approval_code' => 'public_security',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['feature_film'],
                'release_methods' => ['cinema'],
            ],
        ]);

        $response->assertOk();
        $html = (string) data_get($response->json(), 'html');

        $this->assertStringContainsString('Preview Match', $html);
        $this->assertStringNotContainsString('Preview Mismatch', $html);
        $this->assertStringContainsString('Impact Preview', $html);
    }

    public function test_preview_endpoint_lists_overlapping_active_rules(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        ApprovalRoutingRule::query()->create([
            'name' => 'Broad municipalities safety rule',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => [],
                'work_categories' => ['feature_film', 'documentary'],
                'release_methods' => [],
            ],
            'priority' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.approval-routing.preview'), [
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['feature_film'],
                'release_methods' => ['cinema'],
            ],
        ]);

        $response->assertOk();
        $html = (string) data_get($response->json(), 'html');

        $this->assertStringContainsString('Broad municipalities safety rule', $html);
        $this->assertStringContainsString('Existing rule is broader than this proposal.', $html);
        $this->assertStringContainsString('Same target authority', $html);
    }

    public function test_simulator_page_shows_resolved_routes_for_selected_application(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant, $entity] = $this->createApplicantContext();
        $targetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        ApprovalRoutingRule::query()->create([
            'name' => 'Municipal cinema route',
            'request_type' => 'application',
            'approval_code' => 'municipalities',
            'target_entity_id' => $targetEntity->getKey(),
            'conditions' => [
                'project_nationalities' => ['jordanian'],
                'work_categories' => ['feature_film'],
                'release_methods' => ['cinema'],
            ],
            'priority' => 15,
            'is_active' => true,
        ]);

        $application = Application::query()->create([
            'code' => 'REQ-20001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Simulator Match',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-06-01',
            'planned_end_date' => '2026-06-10',
            'project_summary' => 'For routing simulation.',
            'status' => 'submitted',
            'current_stage' => 'authority_review',
            'metadata' => [
                'requirements' => [
                    'required_approvals' => ['municipalities'],
                ],
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.simulator', [
            'application_id' => $application->getKey(),
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Simulator Match')
            ->assertSeeText('Municipal cinema route')
            ->assertSeeText($targetEntity->displayName())
            ->assertSeeText('Active routing rule');
    }

    public function test_simulator_page_can_compare_current_routes_with_unsaved_draft_rule(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        [$applicant, $entity] = $this->createApplicantContext();
        $draftTargetEntity = Entity::query()->where('code', 'greater-amman-municipality')->firstOrFail();

        $application = Application::query()->create([
            'code' => 'REQ-30001',
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $applicant->getKey(),
            'project_name' => 'Draft Compare Match',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-06-01',
            'planned_end_date' => '2026-06-10',
            'project_summary' => 'For draft comparison.',
            'status' => 'submitted',
            'current_stage' => 'authority_review',
            'metadata' => [
                'requirements' => [
                    'required_approvals' => ['public_security'],
                ],
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.approval-routing.simulator', [
            'application_id' => $application->getKey(),
            'draft' => [
                'name' => 'Draft municipal route',
                'approval_code' => 'public_security',
                'target_entity_id' => $draftTargetEntity->getKey(),
                'priority' => 5,
                'conditions' => [
                    'project_nationalities' => ['jordanian'],
                    'work_categories' => ['feature_film'],
                    'release_methods' => ['cinema'],
                ],
            ],
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Current Routing Result')
            ->assertSeeText('With Draft Rule')
            ->assertSeeText('Draft municipal route')
            ->assertSeeText($draftTargetEntity->displayName())
            ->assertSeeText('Draft rule')
            ->assertSeeText('Added route');
    }

    /**
     * @param  array<string, mixed>  $userOverrides
     * @return array{0: User, 1: Entity}
     */
    private function createAuthorityContextForEntity(string $entityCode, array $userOverrides = []): array
    {
        $entity = Entity::query()->where('code', $entityCode)->firstOrFail();

        $user = User::query()->create(array_merge([
            'name' => 'Authority Reviewer',
            'username' => 'authority-reviewer-'.$entityCode,
            'email' => $entityCode.'@example.com',
            'phone' => '079'.str_pad((string) (abs(crc32($entityCode)) % 10000000), 7, '0', STR_PAD_LEFT),
            'status' => 'active',
            'password' => Hash::make('Authority@123'),
        ], $userOverrides));

        $user->entities()->attach($entity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('authority_approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return [$user, $entity];
    }

    /**
     * @param  array<string, mixed>  $userOverrides
     * @param  array<string, mixed>  $entityOverrides
     * @return array{0: User, 1: Entity}
     */
    private function createApplicantContext(array $userOverrides = [], array $entityOverrides = []): array
    {
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create(array_merge([
            'name' => 'Applicant Owner',
            'username' => 'applicant-owner',
            'email' => 'applicant-owner@example.com',
            'phone' => '0793333000',
            'status' => 'active',
            'registration_type' => 'company',
            'password' => Hash::make('Applicant@123'),
        ], $userOverrides));

        $entity = Entity::query()->create(array_merge([
            'group_id' => $group->getKey(),
            'name_en' => 'Applicant Studio',
            'name_ar' => 'Applicant Studio',
            'registration_no' => 'ORG-100',
            'email' => 'studio@applicant.test',
            'phone' => '0793333111',
            'status' => 'active',
            'registration_type' => 'company',
            'metadata' => [
                'address' => 'Amman, Jordan',
            ],
        ], $entityOverrides));

        $user->entities()->attach($entity->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return [$user, $entity];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'project_name' => 'Desert Dreams',
            'project_nationality' => 'jordanian',
            'work_category' => 'feature_film',
            'release_method' => 'cinema',
            'planned_start_date' => '2026-05-01',
            'planned_end_date' => '2026-05-10',
            'estimated_crew_count' => 35,
            'estimated_budget' => 120000,
            'project_summary' => 'A feature film production in Wadi Rum.',
            'producer_name' => 'Local Producer',
            'production_company_name' => 'Studio One',
            'contact_address' => 'Amman',
            'contact_phone' => '065555555',
            'contact_mobile' => '0791111111',
            'contact_fax' => '065555556',
            'contact_email' => 'producer@example.com',
            'liaison_name' => 'Liaison Person',
            'liaison_position' => 'Coordinator',
            'liaison_email' => 'liaison@example.com',
            'liaison_mobile' => '0792222222',
            'director_name' => 'Director Name',
            'director_nationality' => 'Jordanian',
            'director_profile_url' => 'https://example.com/director',
            'international_producer_name' => 'Global Partner',
            'international_producer_company' => 'Global Films',
            'required_approvals' => ['public_security'],
            'supporting_notes' => 'Need desert location and crowd management support.',
        ], $overrides);
    }
}
